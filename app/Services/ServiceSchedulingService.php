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
    // РАСЧЁТ ПОРОГОВ ЗАПРАВКИ
    // ==========================================

    /**
     * Рассчитать порог пробега до заправки для грузовика
     * Формула: (ёмкость бака / расход на 100км) * 100 * коэффициент_запаса
     */
    public function calculateFuelingThreshold(Truck $truck): int
    {
        $tankCapacity = $truck->fuel_capacity; // литры
        $consumption = $truck->fuel_consumption; // литры на 100км

        if ($consumption <= 0) {
            return 500; // значение по умолчанию
        }

        // Максимальный пробег на полном баке
        $maxRange = ($tankCapacity / $consumption) * 100;

        // Применяем коэффициент запаса (например, 0.8 - заправляем при 20% остатка)
        $safetyCoefficient = 0.8;

        return (int) ($maxRange * $safetyCoefficient);
    }

    /**
     * Проверить, нужна ли заправка
     */
    public function needsFueling(Truck $truck): bool
    {
        $threshold = $this->calculateFuelingThreshold($truck);
        return $truck->mileage_since_fuel >= $threshold;
    }

    /**
     * Получить пробег до следующей заправки
     */
    public function getMileageUntilFueling(Truck $truck): int
    {
        $threshold = $this->calculateFuelingThreshold($truck);
        return max(0, $threshold - $truck->mileage_since_fuel);
    }

    // ==========================================
    // РАСЧЁТ ПОРОГОВ ТО
    // ==========================================

    /**
     * Определить следующий тип ТО
     */
    public function getNextTOType(Truck $truck): string
    {
        // Чередование: TO-1 -> TO-2 -> TO-1 -> TO-2
        // После TO-2 снова идёт TO-1
        return $truck->last_to_type === TruckPlannedTask::TO_1
            ? TruckPlannedTask::TO_2
            : TruckPlannedTask::TO_1;
    }

    /**
     * Получить интервал до следующего ТО (в минутах)
     */
    public function getTOInterval(Truck $truck): int
    {
        $nextType = $this->getNextTOType($truck);
        $intervalHours = $nextType === TruckPlannedTask::TO_2
            ? SystemSetting::getTO2Interval()
            : SystemSetting::getTO1Interval();

        return $intervalHours * 60; // конвертируем в минуты
    }

    /**
     * Проверить, нужно ли ТО
     */
    public function needsMaintenance(Truck $truck): bool
    {
        $intervalMinutes = $this->getTOInterval($truck);
        return $truck->moto_minutes_since_to >= $intervalMinutes;
    }

    /**
     * Получить мото-часы до следующего ТО
     */
    public function getMotoHoursUntilTO(Truck $truck): float
    {
        $intervalMinutes = $this->getTOInterval($truck);
        $remaining = $intervalMinutes - $truck->moto_minutes_since_to;
        return max(0, $remaining / 60); // возвращаем в часах
    }

    // ==========================================
    // УЧЁТ ПРОБЕГА И МОТО-ЧАСОВ
    // ==========================================

    /**
     * Добавить пробег грузовику
     */
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

    /**
     * Добавить мото-часы грузовику (в минутах)
     */
    public function addMotoMinutes(Truck $truck, int $minutes): void
    {
        $truck->moto_minutes += $minutes;
        $truck->moto_minutes_since_to += $minutes;
        $truck->save();
    }

    /**
     * Рассчитать и добавить мото-часы за период работы
     */
    public function recordWorkTime(Truck $truck, int $minutesWorked): void
    {
        $this->addMotoMinutes($truck, $minutesWorked);
    }

    // ==========================================
    // УПРАВЛЕНИЕ ПОСТАМИ ОБСЛУЖИВАНИЯ
    // ==========================================

    /**
     * Получить тип поста по типу задачи
     */
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
     * Получить свободные посты для типа задачи
     */
    public function getFreePosts(string $taskType): \Illuminate\Database\Eloquent\Collection
    {
        $postType = $this->getPostTypeByTaskType($taskType);

        return ServicePost::ofType($postType)
            ->free()
            ->get();
    }

    /**
     * Получить занятые посты для типа задачи
     */
    public function getOccupiedPosts(string $taskType): \Illuminate\Database\Eloquent\Collection
    {
        $postType = $this->getPostTypeByTaskType($taskType);

        return ServicePost::ofType($postType)
            ->occupied()
            ->get();
    }

    /**
     * Проверить наличие свободного поста
     */
    public function hasFreePost(string $taskType): bool
    {
        return $this->getFreePosts($taskType)->count() > 0;
    }

    /**
     * Получить количество в очереди для типа задачи
     */
    public function getQueueLength(string $taskType): int
    {
        return TruckPlannedTask::ofType($taskType)
            ->inQueue()
            ->count();
    }

    /**
     * Получить позицию в очереди для грузовика
     */
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

    /**
     * Создать задачу на обслуживание
     */
    public function createTask(Truck $truck, string $taskType, ?string $toType = null, ?int $durationMinutes = null): TruckPlannedTask
    {
        // Если задача уже существует и не завершена - возвращаем существующую
        $existingTask = TruckPlannedTask::where('truck_id', $truck->id)
            ->where('task_type', $taskType)
            ->where('completed', false)
            ->first();

        if ($existingTask) {
            return $existingTask;
        }

        // Определяем длительность
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

        // Определяем позицию в очереди
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

    /**
     * Запланировать заправку
     */
    public function scheduleFueling(Truck $truck): TruckPlannedTask
    {
        return $this->createTask($truck, TruckPlannedTask::TYPE_FUELING);
    }

    /**
     * Запланировать ТО
     */
    public function scheduleMaintenance(Truck $truck): TruckPlannedTask
    {
        $toType = $this->getNextTOType($truck);
        return $this->createTask($truck, TruckPlannedTask::TYPE_MAINTENANCE, $toType);
    }

    /**
     * Запланировать подкачку шин
     */
    public function scheduleTireInflation(Truck $truck): TruckPlannedTask
    {
        return $this->createTask($truck, TruckPlannedTask::TYPE_TIRE_INFLATION);
    }

    /**
     * Запланировать обтяжку колёс
     */
    public function scheduleWheelTightening(Truck $truck): TruckPlannedTask
    {
        return $this->createTask($truck, TruckPlannedTask::TYPE_WHEEL_TIGHTENING);
    }

    // ==========================================
    // ЗАПУСК ОБСЛУЖИВАНИЯ
    // ==========================================

    /**
     * Начать обслуживание (занять пост)
     */
    public function startService(TruckPlannedTask $task): bool
    {
        // Ищем свободный пост
        $freePost = $this->getFreePosts($task->task_type)->first();

        if (!$freePost) {
            return false;
        }

        // Занимаем пост
        $freePost->occupy($task->truck, $task->getDuration());

        // Обновляем задачу
        $task->start($freePost->id);

        // Обновляем статус грузовика
        $task->truck->update([
            'status' => match($task->task_type) {
                TruckPlannedTask::TYPE_FUELING => Truck::STATUS_FUELING,
                TruckPlannedTask::TYPE_MAINTENANCE => Truck::STATUS_MAINTENANCE,
                default => 'service',
            },
        ]);

        return true;
    }

    /**
     * Завершить обслуживание
     */
    public function completeService(TruckPlannedTask $task): void
    {
        $task->complete();

        // Устанавливаем грузовик свободным
        $task->truck->update(['status' => Truck::STATUS_FREE]);

        // Запускаем следующую задачу из очереди
        $this->processQueue($task->task_type);
    }

    /**
     * Обработать очередь для типа задачи
     */
    public function processQueue(string $taskType): void
    {
        if (!$this->hasFreePost($taskType)) {
            return;
        }

        // Берём следующую задачу из очереди
        $nextTask = TruckPlannedTask::ofType($taskType)
            ->inQueue()
            ->orderBy('queue_position')
            ->first();

        if ($nextTask) {
            $this->startService($nextTask);

            // Пересчитываем позиции в очереди
            $this->recalculateQueuePositions($taskType);
        }
    }

    /**
     * Пересчитать позиции в очереди
     */
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
    // ПРОВЕРКА ПРИ НАЧАЛЕ СМЕНЫ
    // ==========================================

    /**
     * Проверить и запланировать обслуживание при начале смены
     */
    public function checkAndScheduleAtShiftStart(Truck $truck): array
    {
        $plannedTasks = [];

        // Проверяем заправку
        if ($this->needsFueling($truck)) {
            $plannedTasks[] = $this->scheduleFueling($truck);
        }

        // Проверяем ТО
        if ($this->needsMaintenance($truck)) {
            $plannedTasks[] = $this->scheduleMaintenance($truck);
        }

        return $plannedTasks;
    }

    /**
     * Получить запланированные задачи для грузовика
     */
    public function getPendingTasks(Truck $truck): \Illuminate\Database\Eloquent\Collection
    {
        return TruckPlannedTask::where('truck_id', $truck->id)
            ->where('completed', false)
            ->orderBy('queue_position')
            ->get();
    }

    /**
     * Получить все активные задачи (в процессе)
     */
    public function getActiveTasks(): \Illuminate\Database\Eloquent\Collection
    {
        return TruckPlannedTask::inProgress()
            ->with(['truck', 'servicePost'])
            ->get();
    }

    /**
     * Получить все задачи в очереди
     */
    public function getQueuedTasks(): \Illuminate\Database\Eloquent\Collection
    {
        return TruckPlannedTask::inQueue()
            ->with(['truck'])
            ->orderBy('queue_position')
            ->get();
    }

    // ==========================================
    // ЗАПРОС ВОДИТЕЛЯ (подкачка/обтяжка)
    // ==========================================

    /**
     * Обработать запрос водителя на обслуживание
     */
    public function handleDriverRequest(Truck $truck, string $taskType): array
    {
        $result = [
            'success' => false,
            'message' => '',
            'task' => null,
            'queue_position' => null,
            'post_name' => null,            
        ];

        // Проверяем, есть ли активный рейс
        $hasActiveTrip = $truck->currentTrip()->exists();

        // Создаём задачу
        $task = $this->createTask($truck, $taskType);
        $result['task'] = $task;

        // Проверяем наличие свободных постов
        $freePost = $this->getFreePosts($taskType)->first();

        if ($freePost) {
            // Есть свободный пост
            if ($hasActiveTrip) {
                // Нужно завершить рейс сначала
                $result['message'] = 'Заявка принята. Завершите текущий рейс, затем отправляйтесь на пост "' . $freePost->name . '".';
                $result['needs_complete_trip'] = true;
                $result['post_name'] = $freePost->name;
            } else {
                // Можно сразу начинать - занимаем пост
                $freePost->occupy($truck, $task->getDuration());
                $task->start($freePost->id);

                // Обновляем статус грузовика
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
            // Нет свободных постов - ставим в очередь
            $result['queue_position'] = $task->queue_position;
            $result['message'] = "Посты заняты. Вы в очереди, позиция: {$task->queue_position}";

            if ($hasActiveTrip) {
                $result['message'] .= ' Завершите текущий рейс.';
                $result['needs_complete_trip'] = true;
            }
        }

        return $result;
    }

    /**
     * Отменить задачу
     */
    public function cancelTask(TruckPlannedTask $task): bool
    {
        if ($task->started_at) {
            return false; // Нельзя отменить начатую задачу
        }

        $taskType = $task->task_type;
        $task->delete();

        // Пересчитываем очередь
        $this->recalculateQueuePositions($taskType);

        return true;
    }
}
