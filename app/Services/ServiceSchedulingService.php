<?php

namespace App\Services;

use App\Models\ServicePost;
use App\Models\Truck;
use App\Models\TruckPlannedTask;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Log;

class ServiceSchedulingService
{
    // ==========================================
    // ПЛАНИРОВАНИЕ НА СМЕНУ (НОВАЯ ЛОГИКА)
    // ==========================================

    /**
     * Рассчитать критичность для ТО
     * Отрицательное значение = просрочено (высший приоритет)
     * Чем меньше значение — тем критичнее
     */
    public function calculateTOScore(Truck $truck): float
    {
        $bufferHours = SystemSetting::getServiceToBufferHours();
        $intervalMinutes = $this->getTOInterval($truck);
        $bufferMinutes = $bufferHours * 60;
        
        // Порог с учётом буфера
        $thresholdMinutes = $intervalMinutes - $bufferMinutes;
        
        // Оставшиеся минуты до отправки на ТО
        $remainingMinutes = $thresholdMinutes - $truck->moto_minutes_since_to;
        
        return $remainingMinutes / 60; // возвращаем в часах
    }

    /**
     * Рассчитать критичность для заправки
     * Возвращает % остатка топлива (меньше = критичнее)
     */
    public function calculateFuelingScore(Truck $truck): float
    {
        $bufferPercent = SystemSetting::getServiceFuelingBufferPercent();
        $threshold = $this->calculateFuelingThreshold($truck);
        
        // Пробег до заправки (отрицательный = просрочено)
        $remainingKm = $threshold - $truck->mileage_since_fuel;
        
        // В процентах от порога
        return ($remainingKm / $threshold) * 100;
    }

    /**
     * Проверить, нужен ли ТО с учётом буфера
     */
    public function needsTOWithBuffer(Truck $truck): bool
    {
        return $this->calculateTOScore($truck) <= 0;
    }

    /**
     * Проверить, нужна ли заправка с учётом буфера
     */
    public function needsFuelingWithBuffer(Truck $truck): bool
    {
        return $this->calculateFuelingScore($truck) <= 0;
    }

    /**
     * Спланировать обслуживание на смену для всех грузовиков
     * Сортировка по критичности, формирование очередей
     */
    public function planShiftServiceForAll(): array
    {
        $results = [
            'total_trucks' => 0,
            'to_planned' => 0,
            'fueling_planned' => 0,
            'details' => [],
        ];

        // Получаем все активные грузовики (не в поломке)
        $trucks = Truck::where('status', '!=', 'breakdown')
            ->whereNotNull('driver_id')
            ->with('truckModel')
            ->get();

        $results['total_trucks'] = $trucks->count();

        // Собираем грузовики, которым нужен ТО
        $toNeeded = [];
        // Собираем грузовики, которым нужна заправка
        $fuelingNeeded = [];

        foreach ($trucks as $truck) {
            // Проверяем ТО
            $toScore = $this->calculateTOScore($truck);
            if ($toScore <= 0) {
                $toNeeded[] = [
                    'truck' => $truck,
                    'score' => $toScore,
                ];
            }

            // Проверяем заправку
            $fuelingScore = $this->calculateFuelingScore($truck);
            if ($fuelingScore <= 0) {
                $fuelingNeeded[] = [
                    'truck' => $truck,
                    'score' => $fuelingScore,
                ];
            }
        }

        // Сортируем по критичности (score по возрастанию)
        usort($toNeeded, fn($a, $b) => $a['score'] <=> $b['score']);
        usort($fuelingNeeded, fn($a, $b) => $a['score'] <=> $b['score']);

        // Очищаем старые незавершённые задачи ТО и заправки (не шины!)
        TruckPlannedTask::whereIn('task_type', [
            TruckPlannedTask::TYPE_FUELING,
            TruckPlannedTask::TYPE_MAINTENANCE,
        ])
            ->where('completed', false)
            ->whereNull('started_at')
            ->delete();

        // Создаём задачи ТО с позициями в очереди
        $toPosition = 1;
        foreach ($toNeeded as $item) {
            $truck = $item['truck'];
            $task = $this->createTask($truck, TruckPlannedTask::TYPE_MAINTENANCE, $this->getNextTOType($truck));
            $task->update(['queue_position' => $toPosition++]);
            
            $results['to_planned']++;
            $results['details'][] = [
                'truck_number' => $truck->number,
                'type' => 'ТО',
                'score' => round($item['score'], 1),
                'queue_position' => $task->queue_position,
            ];
        }

        // Создаём задачи заправки с позициями в очереди
        $fuelingPosition = 1;
        foreach ($fuelingNeeded as $item) {
            $truck = $item['truck'];
            $task = $this->createTask($truck, TruckPlannedTask::TYPE_FUELING);
            $task->update(['queue_position' => $fuelingPosition++]);
            
            $results['fueling_planned']++;
            $results['details'][] = [
                'truck_number' => $truck->number,
                'type' => 'Заправка',
                'score' => round($item['score'], 1) . '%',
                'queue_position' => $task->queue_position,
            ];
        }

        Log::info("Планирование обслуживания на смену", $results);

        return $results;
    }

    /**
     * Получить прогноз времени ожидания в очереди
     */
    public function estimateWaitTime(TruckPlannedTask $task): ?int
    {
        // Если задача уже началась — нет ожидания
        if ($task->started_at) {
            return 0;
        }

        $taskType = $task->task_type;
        $position = $task->queue_position;

        // Получаем активные посты этого типа
        $activePosts = ServicePost::ofType($this->getPostTypeByTaskType($taskType))
            ->active()
            ->get();

        if ($activePosts->isEmpty()) {
            return null; // Нет активных постов
        }

        // Получаем задачи впереди в очереди
        $tasksAhead = TruckPlannedTask::ofType($taskType)
            ->inQueue()
            ->where('queue_position', '<', $position)
            ->orderBy('queue_position')
            ->get();

        // Получаем задачи в процессе на постах
        $tasksInProgress = TruckPlannedTask::ofType($taskType)
            ->inProgress()
            ->with('servicePost')
            ->get();

        // Рассчитываем время
        $totalMinutes = 0;
        $postsCount = $activePosts->count();

        // Время до освобождения текущих постов
        $postFreeTimes = [];
        foreach ($activePosts as $post) {
            $postFreeTimes[$post->id] = $post->getMinutesUntilFree() ?? 0;
        }

        // Распределяем задачи впереди по постам
        $taskIndex = 0;
        foreach ($tasksAhead as $aheadTask) {
            // Находим пост с минимальным временем освобождения
            asort($postFreeTimes);
            $minPostId = array_key_first($postFreeTimes);
            
            // Добавляем длительность задачи к этому посту
            $postFreeTimes[$minPostId] += $aheadTask->getDuration();
            $taskIndex++;
        }

        // Теперь находим, когда освободится пост для нашей задачи
        asort($postFreeTimes);
        $estimatedMinutes = reset($postFreeTimes);

        return max(0, $estimatedMinutes);
    }

    /**
     * Форматировать время ожидания
     */
    public function formatWaitTime(?int $minutes): string
    {
        if ($minutes === null) {
            return '—';
        }
        if ($minutes <= 0) {
            return 'Сразу';
        }
        if ($minutes < 60) {
            return "{$minutes} мин";
        }
        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;
        return "{$hours}ч {$mins}мин";
    }

    /**
     * Переместить задачи грузовика в конец очереди (при поломке)
     */
    public function moveTasksToEndOfQueue(Truck $truck): void
    {
        $tasks = TruckPlannedTask::where('truck_id', $truck->id)
            ->where('completed', false)
            ->whereNull('started_at')
            ->get();

        foreach ($tasks as $task) {
            $taskType = $task->task_type;
            
            // Получаем максимальную позицию в очереди для этого типа
            $maxPosition = TruckPlannedTask::ofType($taskType)
                ->inQueue()
                ->max('queue_position') ?? 0;
            
            $task->update(['queue_position' => $maxPosition + 1]);
            
            Log::info("Задача перемещена в конец очереди при поломке", [
                'truck_id' => $truck->id,
                'task_type' => $taskType,
                'new_position' => $maxPosition + 1,
            ]);
        }
    }

    // ==========================================
    // СТАРЫЕ МЕТОДЫ (с обновлениями)
    // ==========================================

    /**
     * Проверить, нужно ли запланировать ТО на текущую смену
     */
    public function needsMaintenanceThisShift(Truck $truck, int $shiftHours = 12): bool
    {
        $score = $this->calculateTOScore($truck);
        return $score <= $shiftHours;
    }

    /**
     * Проверить, нужно ли запланировать заправку на текущую смену
     */
    public function needsFuelingThisShift(Truck $truck, int $shiftDistanceKm = 200): bool
    {
        $threshold = $this->calculateFuelingThreshold($truck);
        $remainingKm = $threshold - $truck->mileage_since_fuel;
        return $remainingKm <= $shiftDistanceKm;
    }

    /**
     * Спланировать обслуживание на смену для грузовика
     */
    public function planServiceForShift(Truck $truck, int $shiftHours = 12, int $shiftDistanceKm = 200): array
    {
        $plannedTasks = [];

        if ($this->needsFuelingThisShift($truck, $shiftDistanceKm)) {
            $task = $this->scheduleFueling($truck);
            $plannedTasks[] = [
                'task' => $task,
                'type' => 'fueling',
                'reason' => 'Заправка запланирована на смену',
                'priority' => 1,
            ];
        }

        if ($this->needsMaintenanceThisShift($truck, $shiftHours)) {
            $task = $this->scheduleMaintenance($truck);
            $plannedTasks[] = [
                'task' => $task,
                'type' => 'maintenance',
                'reason' => 'ТО запланировано на смену',
                'priority' => 2,
                'to_type' => $task->to_type,
            ];
        }

        return $plannedTasks;
    }

    /**
     * Получить приоритетную задачу для грузовика
     */
    public function getNextServiceTask(Truck $truck): ?TruckPlannedTask
    {
        return TruckPlannedTask::where('truck_id', $truck->id)
            ->where('completed', false)
            ->whereNull('started_at')
            ->orderBy('queue_position')
            ->first();
    }

    /**
     * Отправить грузовик на следующее запланированное обслуживание
     */
    public function sendToNextService(Truck $truck): ?array
    {
        $task = $this->getNextServiceTask($truck);

        if (!$task) {
            return null;
        }

        $freePost = $this->getFreePosts($task->task_type)->first();

        if ($freePost) {
            $freePost->occupy($truck, $task->getDuration());
            $task->start($freePost->id);

            $truck->update([
                'status' => $task->task_type === TruckPlannedTask::TYPE_FUELING
                    ? 'fueling'
                    : 'maintenance',
            ]);

            return [
                'success' => true,
                'task' => $task,
                'post' => $freePost,
                'message' => "Отправляйтесь на пост \"{$freePost->name}\"",
            ];
        } else {
            return [
                'success' => false,
                'task' => $task,
                'queue_position' => $task->queue_position,
                'message' => "Посты заняты. Вы в очереди, позиция: {$task->queue_position}",
            ];
        }
    }

    // ==========================================
    // РАСЧЁТ ПОРОГОВ ЗАПРАВКИ
    // ==========================================

    public function calculateFuelingThreshold(Truck $truck): int
    {
        $truckModel = $truck->truckModel;
        $tankCapacity = $truckModel?->fuel_capacity ?? 500;
        $consumption = $truckModel?->fuel_consumption ?? 35;

        if ($consumption <= 0) {
            return 500;
        }

        $maxRange = ($tankCapacity / $consumption) * 100;
        $safetyCoefficient = 0.8;

        return (int) ($maxRange * $safetyCoefficient);
    }

    public function needsFueling(Truck $truck): bool
    {
        $threshold = $this->calculateFuelingThreshold($truck);
        return $truck->mileage_since_fuel >= $threshold;
    }

    public function getMileageUntilFueling(Truck $truck): int
    {
        $threshold = $this->calculateFuelingThreshold($truck);
        return max(0, $threshold - $truck->mileage_since_fuel);
    }

    // ==========================================
    // РАСЧЁТ ПОРОГОВ ТО
    // ==========================================

    public function getNextTOType(Truck $truck): string
    {
        return $truck->last_to_type === TruckPlannedTask::TO_1
            ? TruckPlannedTask::TO_2
            : TruckPlannedTask::TO_1;
    }

    public function getTOInterval(Truck $truck): int
    {
        $nextType = $this->getNextTOType($truck);
        $intervalHours = $nextType === TruckPlannedTask::TO_2
            ? SystemSetting::getTO2Interval()
            : SystemSetting::getTO1Interval();

        return $intervalHours * 60;
    }

    public function needsMaintenance(Truck $truck): bool
    {
        $intervalMinutes = $this->getTOInterval($truck);
        return $truck->moto_minutes_since_to >= $intervalMinutes;
    }

    public function getMotoHoursUntilTO(Truck $truck): float
    {
        $intervalMinutes = $this->getTOInterval($truck);
        $remaining = $intervalMinutes - $truck->moto_minutes_since_to;
        return max(0, $remaining / 60);
    }

    // ==========================================
    // УЧЁТ ПРОБЕГА И МОТО-ЧАСОВ
    // ==========================================

    public function addMileage(Truck $truck, float $distance, bool $isEmptyRun = false): void
    {
        if ($isEmptyRun) {
            $coefficient = SystemSetting::getEmptyRunCoefficient();
            $distance = $distance * $coefficient;
        }

        $truck->mileage += (int) $distance;
        $truck->mileage_since_fuel += (int) $distance;
        $truck->save();
    }

    public function addMotoMinutes(Truck $truck, int $minutes): void
    {
        $truck->moto_minutes += $minutes;
        $truck->moto_minutes_since_to += $minutes;
        $truck->save();
    }

    public function recordWorkTime(Truck $truck, int $minutesWorked): void
    {
        $this->addMotoMinutes($truck, $minutesWorked);
    }

    // ==========================================
    // УПРАВЛЕНИЕ ПОСТАМИ ОБСЛУЖИВАНИЯ
    // ==========================================

    protected function getPostTypeByTaskType(string $taskType): string
    {
        return match($taskType) {
            TruckPlannedTask::TYPE_FUELING => ServicePost::TYPE_FUELING,
            TruckPlannedTask::TYPE_MAINTENANCE => ServicePost::TYPE_MAINTENANCE,
            TruckPlannedTask::TYPE_TIRE_INFLATION,
            TruckPlannedTask::TYPE_WHEEL_TIGHTENING => ServicePost::TYPE_TIRE_SERVICE,
            default => ServicePost::TYPE_TIRE_SERVICE,
        };
    }

    /**
     * Получить свободные АКТИВНЫЕ посты для типа задачи
     */
    public function getFreePosts(string $taskType): \Illuminate\Database\Eloquent\Collection
    {
        $postType = $this->getPostTypeByTaskType($taskType);

        return ServicePost::ofType($postType)
            ->active()
            ->free()
            ->get();
    }

    public function getOccupiedPosts(string $taskType): \Illuminate\Database\Eloquent\Collection
    {
        $postType = $this->getPostTypeByTaskType($taskType);

        return ServicePost::ofType($postType)
            ->occupied()
            ->get();
    }

    public function hasFreePost(string $taskType): bool
    {
        return $this->getFreePosts($taskType)->count() > 0;
    }

    public function getQueueLength(string $taskType): int
    {
        return TruckPlannedTask::ofType($taskType)
            ->inQueue()
            ->count();
    }

    public function getQueuePosition(Truck $truck, string $taskType): ?int
    {
        $task = TruckPlannedTask::ofType($taskType)
            ->where('truck_id', $truck->id)
            ->inQueue()
            ->first();

        return $task?->queue_position;
    }

    // ==========================================
    // СОЗДАНИЕ ЗАДАЧ ОБСЛУЖИВАНИЯ
    // ==========================================

    public function createTask(Truck $truck, string $taskType, ?string $toType = null, ?int $durationMinutes = null): TruckPlannedTask
    {
        $existingTask = TruckPlannedTask::where('truck_id', $truck->id)
            ->where('task_type', $taskType)
            ->where('completed', false)
            ->first();

        if ($existingTask) {
            return $existingTask;
        }

        if (!$durationMinutes) {
            $durationMinutes = match($taskType) {
                TruckPlannedTask::TYPE_FUELING => TruckPlannedTask::DURATION_FUELING,
                TruckPlannedTask::TYPE_MAINTENANCE => $toType === TruckPlannedTask::TO_2
                    ? TruckPlannedTask::DURATION_TO_2
                    : TruckPlannedTask::DURATION_TO_1,
                TruckPlannedTask::TYPE_TIRE_INFLATION => TruckPlannedTask::DURATION_TIRE_INFLATION,
                TruckPlannedTask::TYPE_WHEEL_TIGHTENING => TruckPlannedTask::DURATION_WHEEL_TIGHTENING,
                default => 30,
            };
        }

        $queuePosition = $this->getQueueLength($taskType) + 1;

        $task = TruckPlannedTask::create([
            'truck_id' => $truck->id,
            'task_type' => $taskType,
            'to_type' => $toType,
            'duration_minutes' => $durationMinutes,
            'queue_position' => $queuePosition,
            'scheduled_at' => now(),
        ]);

        return $task;
    }

    public function scheduleFueling(Truck $truck): TruckPlannedTask
    {
        return $this->createTask($truck, TruckPlannedTask::TYPE_FUELING);
    }

    public function scheduleMaintenance(Truck $truck): TruckPlannedTask
    {
        $toType = $this->getNextTOType($truck);
        return $this->createTask($truck, TruckPlannedTask::TYPE_MAINTENANCE, $toType);
    }

    public function scheduleTireInflation(Truck $truck): TruckPlannedTask
    {
        return $this->createTask($truck, TruckPlannedTask::TYPE_TIRE_INFLATION);
    }

    public function scheduleWheelTightening(Truck $truck): TruckPlannedTask
    {
        return $this->createTask($truck, TruckPlannedTask::TYPE_WHEEL_TIGHTENING);
    }

    // ==========================================
    // ЗАПУСК ОБСЛУЖИВАНИЯ
    // ==========================================

    public function startService(TruckPlannedTask $task): bool
    {
        $freePost = $this->getFreePosts($task->task_type)->first();

        if (!$freePost) {
            return false;
        }

        $freePost->occupy($task->truck, $task->getDuration());
        $task->start($freePost->id);

        $task->truck->update([
            'status' => match($task->task_type) {
                TruckPlannedTask::TYPE_FUELING => Truck::STATUS_FUELING,
                TruckPlannedTask::TYPE_MAINTENANCE => Truck::STATUS_MAINTENANCE,
                default => 'service',
            },
        ]);

        return true;
    }

    public function completeService(TruckPlannedTask $task): void
    {
        $task->complete();
        $task->truck->update(['status' => Truck::STATUS_FREE]);
        $this->processQueue($task->task_type);
    }

    public function processQueue(string $taskType): void
    {
        if (!$this->hasFreePost($taskType)) {
            return;
        }

        $nextTask = TruckPlannedTask::ofType($taskType)
            ->inQueue()
            ->orderBy('queue_position')
            ->first();

        if ($nextTask) {
            $this->startService($nextTask);
            $this->recalculateQueuePositions($taskType);
        }
    }

    protected function recalculateQueuePositions(string $taskType): void
    {
        $tasks = TruckPlannedTask::ofType($taskType)
            ->inQueue()
            ->orderBy('queue_position')
            ->get();

        foreach ($tasks as $index => $task) {
            $task->update(['queue_position' => $index + 1]);
        }
    }

    // ==========================================
    // ДРУГИЕ МЕТОДЫ
    // ==========================================

    public function checkAndScheduleAtShiftStart(Truck $truck): array
    {
        $plannedTasks = [];

        if ($this->needsFueling($truck)) {
            $plannedTasks[] = $this->scheduleFueling($truck);
        }

        if ($this->needsMaintenance($truck)) {
            $plannedTasks[] = $this->scheduleMaintenance($truck);
        }

        return $plannedTasks;
    }

    public function getPendingTasks(Truck $truck): \Illuminate\Database\Eloquent\Collection
    {
        return TruckPlannedTask::where('truck_id', $truck->id)
            ->where('completed', false)
            ->orderBy('queue_position')
            ->get();
    }

    public function getActiveTasks(): \Illuminate\Database\Eloquent\Collection
    {
        return TruckPlannedTask::inProgress()
            ->with(['truck', 'servicePost'])
            ->get();
    }

    public function getQueuedTasks(): \Illuminate\Database\Eloquent\Collection
    {
        return TruckPlannedTask::inQueue()
            ->with(['truck'])
            ->orderBy('queue_position')
            ->get();
    }

    public function handleDriverRequest(Truck $truck, string $taskType): array
    {
        $result = [
            'success' => false,
            'message' => '',
            'task' => null,
            'queue_position' => null,
            'post_name' => null,
        ];

        $hasActiveTrip = $truck->currentTrip()->exists();

        $task = $this->createTask($truck, $taskType);
        $result['task'] = $task;

        $freePost = $this->getFreePosts($taskType)->first();

        if ($freePost) {
            if ($hasActiveTrip) {
                $result['message'] = 'Заявка принята. Завершите текущий рейс, затем отправляйтесь на пост "' . $freePost->name . '".';
                $result['needs_complete_trip'] = true;
                $result['post_name'] = $freePost->name;
            } else {
                $freePost->occupy($truck, $task->getDuration());
                $task->start($freePost->id);

                $truck->update([
                    'status' => match($taskType) {
                        TruckPlannedTask::TYPE_FUELING => 'fueling',
                        TruckPlannedTask::TYPE_MAINTENANCE => 'maintenance',
                        default => 'service',
                    },
                ]);

                $result['success'] = true;
                $result['post_name'] = $freePost->name;
                $result['message'] = 'Свободный пост найден. Отправляйтесь на пост "' . $freePost->name . '".';
            }
        } else {
            $result['queue_position'] = $task->queue_position;
            $result['message'] = "Посты заняты. Вы в очереди, позиция: {$task->queue_position}";

            if ($hasActiveTrip) {
                $result['message'] .= ' Завершите текущий рейс.';
                $result['needs_complete_trip'] = true;
            }
        }

        return $result;
    }

    public function cancelTask(TruckPlannedTask $task): bool
    {
        if ($task->started_at) {
            return false;
        }

        $taskType = $task->task_type;
        $task->delete();

        $this->recalculateQueuePositions($taskType);

        return true;
    }
}
