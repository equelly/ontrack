<?php

namespace App\Services;

use App\Models\Truck;
use App\Models\ServicePost;
use App\Models\TruckPlannedTask;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ShiftPlanningService
{
    // Приоритеты типов обслуживания (чем выше число - тем выше приоритет)
    const PRIORITY_WHEEL = 100;      // Высший приоритет - безопасность
    const PRIORITY_TO = 50;          // Средний приоритет
    const PRIORITY_FUELING = 10;     // Низший приоритет

    /**
     * Выполнить планирование обслуживания на смену
     */
    public function planShift(): array
    {
        $result = [
            'planned' => 0,
            'queued' => 0,
            'errors' => [],
            'details' => [],
        ];

        // Получаем настройки
        $toBufferHours = SystemSetting::getServiceToBufferHours();
        $fuelingBufferPercent = SystemSetting::getServiceFuelingBufferPercent();
        $to1Interval = SystemSetting::getTO1Interval();
        $to2Interval = SystemSetting::getTO2Interval();

        // Получаем все активные грузовики (не в поломке)
        $trucks = Truck::where('status', '!=', Truck::STATUS_BREAKDOWN)
            ->with(['plannedTasks' => function ($q) {
                $q->where('completed', false)->orderBy('queue_position');
            }])
            ->get();

        foreach ($trucks as $truck) {
            $truckNeeds = $this->analyzeTruckNeeds($truck, $toBufferHours, $fuelingBufferPercent, $to1Interval, $to2Interval);

            if (!empty($truckNeeds)) {
                $plannedTasks = $this->createPlannedTasks($truck, $truckNeeds);
                if (!empty($plannedTasks)) {
                    $result['planned'] += count($plannedTasks);
                    $result['details'][] = [
                        'truck' => $truck->number,
                        'tasks' => array_map(fn($t) => $t->getTypeLabel(), $plannedTasks),
                    ];
                }
            }
        }

        // Распределяем по очередям и постам
        $this->assignQueuesAndStart();

        $result['queued'] = TruckPlannedTask::pending()->count();

        return $result;
    }

    /**
     * Анализировать потребности грузовика в обслуживании
     */
    protected function analyzeTruckNeeds(Truck $truck, int $toBufferHours, int $fuelingBufferPercent, int $to1Interval, int $to2Interval): array
    {
        $needs = [];

        // Проверяем потребность в ТО
        $motoHoursSinceTO = $truck->moto_hours_since_to;
        $lastTOType = $truck->last_to_type;

        // Определяем ближайшее ТО
        $nextTO = $this->determineNextTO($motoHoursSinceTO, $lastTOType, $to1Interval, $to2Interval);
        if ($nextTO && ($nextTO['hours_until'] <= $toBufferHours)) {
            $needs[] = [
                'type' => TruckPlannedTask::TYPE_MAINTENANCE,
                'to_type' => $nextTO['type'],
                'priority' => self::PRIORITY_TO,
                'urgent' => $nextTO['hours_until'] <= 0,
                'reason' => "ТО-{$nextTO['type']} через {$nextTO['hours_until']} мото-часов",
            ];
        }

        // Проверяем потребность в заправке
        // Если топливо <= 10%, делаем ТО сначала, потом заправку
        if ($truck->fuel_level <= $fuelingBufferPercent) {
            $needs[] = [
                'type' => TruckPlannedTask::TYPE_FUELING,
                'priority' => self::PRIORITY_FUELING,
                'urgent' => $truck->fuel_level <= 10,
                'reason' => "Топливо: {$truck->fuel_level}%",
            ];
        }

        // Сортируем по приоритету (высший приоритет первым)
        // Но если топливо <= 10%, ТО должно быть ПЕРЕД заправкой
        usort($needs, function ($a, $b) use ($truck) {
            // Особый случай: топливо <= 10%, ТО должно быть перед заправкой
            if ($truck->fuel_level <= 10) {
                if ($a['type'] === TruckPlannedTask::TYPE_MAINTENANCE && $b['type'] === TruckPlannedTask::TYPE_FUELING) {
                    return -1; // ТО раньше заправки
                }
                if ($a['type'] === TruckPlannedTask::TYPE_FUELING && $b['type'] === TruckPlannedTask::TYPE_MAINTENANCE) {
                    return 1; // Заправка после ТО
                }
            }

            // Обычная сортировка по приоритету
            return $b['priority'] <=> $a['priority'];
        });

        return $needs;
    }

    /**
     * Определить следующее ТО
     */
    protected function determineNextTO(float $motoHoursSinceTO, ?string $lastTOType, int $to1Interval, int $to2Interval): ?array
    {
        // После ТО-2 следующее должно быть ТО-1
        // После ТО-1 следующее может быть ТО-1 или ТО-2 в зависимости от наработки

        if ($lastTOType === TruckPlannedTask::TO_2) {
            // После ТО-2 всегда идёт ТО-1
            $hoursUntilTO1 = $to1Interval - $motoHoursSinceTO;
            return [
                'type' => TruckPlannedTask::TO_1,
                'hours_until' => $hoursUntilTO1,
                'interval' => $to1Interval,
            ];
        }

        // Если последним было ТО-1 или не было, проверяем оба интервала
        $hoursUntilTO1 = $to1Interval - $motoHoursSinceTO;
        $hoursUntilTO2 = $to2Interval - $motoHoursSinceTO;

        // Ближайшее ТО
        if ($hoursUntilTO2 <= $hoursUntilTO1 && $hoursUntilTO2 <= $to1Interval) {
            return [
                'type' => TruckPlannedTask::TO_2,
                'hours_until' => $hoursUntilTO2,
                'interval' => $to2Interval,
            ];
        }

        if ($hoursUntilTO1 <= 0) {
            return [
                'type' => TruckPlannedTask::TO_1,
                'hours_until' => $hoursUntilTO1,
                'interval' => $to1Interval,
            ];
        }

        // Если ни одно ТО не требуется скоро
        if ($hoursUntilTO1 > $to1Interval) {
            return null;
        }

        return [
            'type' => TruckPlannedTask::TO_1,
            'hours_until' => $hoursUntilTO1,
            'interval' => $to1Interval,
        ];
    }

    /**
     * Создать запланированные задачи для грузовика
     */
    protected function createPlannedTasks(Truck $truck, array $needs): array
    {
        $createdTasks = [];

        DB::transaction(function () use ($truck, $needs, &$createdTasks) {
            foreach ($needs as $need) {
                // Проверяем, нет ли уже такой задачи
                $existingTask = TruckPlannedTask::where('truck_id', $truck->id)
                    ->where('task_type', $need['type'])
                    ->where('to_type', $need['to_type'] ?? null)
                    ->where('completed', false)
                    ->first();

                if ($existingTask) {
                    continue; // Уже есть такая задача
                }

                $task = TruckPlannedTask::create([
                    'truck_id' => $truck->id,
                    'task_type' => $need['type'],
                    'to_type' => $need['to_type'] ?? null,
                    'scheduled_at' => now(),
                    'completed' => false,
                    'notes' => $need['reason'] ?? null,
                ]);

                $createdTasks[] = $task;
            }
        });

        return $createdTasks;
    }

    /**
     * Назначить очереди и начать обслуживание
     */
    public function assignQueuesAndStart(): void
    {
        // Получаем все ожидающие задачи, сортируем по приоритету
        $pendingTasks = TruckPlannedTask::pending()
            ->with('truck')
            ->get()
            ->sortByDesc(function ($task) {
                return $this->getTaskPriority($task);
            });

        // Разделяем по типам для отдельных очередей
        $fuelingQueue = $pendingTasks->where('task_type', TruckPlannedTask::TYPE_FUELING);
        $maintenanceQueue = $pendingTasks->where('task_type', TruckPlannedTask::TYPE_MAINTENANCE);
        $wheelQueue = $pendingTasks->where('task_type', TruckPlannedTask::TYPE_TIRE_INFLATION)
            ->merge($pendingTasks->where('task_type', TruckPlannedTask::TYPE_WHEEL_TIGHTENING));

        // Назначаем позиции в очередях
        $this->assignQueuePositions($fuelingQueue, ServicePost::TYPE_FUELING);
        $this->assignQueuePositions($maintenanceQueue, ServicePost::TYPE_MAINTENANCE);
        $this->assignQueuePositions($wheelQueue, ServicePost::TYPE_TIRE_SERVICE);

        // Пытаемся начать обслуживание для доступных постов
        $this->tryStartService(ServicePost::TYPE_FUELING, $fuelingQueue);
        $this->tryStartService(ServicePost::TYPE_MAINTENANCE, $maintenanceQueue);
        $this->tryStartService(ServicePost::TYPE_TIRE_SERVICE, $wheelQueue);
    }

    /**
     * Получить приоритет задачи
     */
    protected function getTaskPriority(TruckPlannedTask $task): int
    {
        return match($task->task_type) {
            TruckPlannedTask::TYPE_TIRE_INFLATION,
            TruckPlannedTask::TYPE_WHEEL_TIGHTENING => self::PRIORITY_WHEEL,
            TruckPlannedTask::TYPE_MAINTENANCE => self::PRIORITY_TO,
            TruckPlannedTask::TYPE_FUELING => self::PRIORITY_FUELING,
            default => 0,
        };
    }

    /**
     * Назначить позиции в очереди
     */
    protected function assignQueuePositions($tasks, string $postType): void
    {
        $position = 1;

        // Сначала те, что уже в процессе
        foreach ($tasks->whereNotNull('started_at') as $task) {
            $task->update(['queue_position' => 0]); // В процессе
        }

        // Потом ожидающие
        foreach ($tasks->whereNull('started_at') as $task) {
            $task->update(['queue_position' => $position++]);
        }
    }

    /**
     * Попытаться начать обслуживание на постах
     */
    protected function tryStartService(string $postType, $queue): void
    {
        // Получаем свободные активные посты
        $freePosts = ServicePost::getFreeActivePosts($postType);

        if ($freePosts->isEmpty()) {
            return;
        }

        // Получаем задачи в очереди (не начатые)
        $waitingTasks = $queue->whereNull('started_at')->sortBy('queue_position');

        foreach ($freePosts as $post) {
            $task = $waitingTasks->first();
            if (!$task) {
                break;
            }

            // Проверяем, что грузовик свободен
            $truck = $task->truck;
            if (!in_array($truck->status, [Truck::STATUS_FREE, 'completed', 'breakdown'])) {
                continue;
            }

            // Начинаем обслуживание
            $this->startTaskOnPost($task, $post);
            $waitingTasks = $waitingTasks->except($task->id);
        }
    }

    /**
     * Начать задачу на посту
     */
    protected function startTaskOnPost(TruckPlannedTask $task, ServicePost $post): void
    {
        DB::transaction(function () use ($task, $post) {
            $duration = $task->getDuration();

            // Занимаем пост
            $post->occupy($task->truck, $duration);

            // Начинаем задачу
            $task->start($post->id);

            // Обновляем статус грузовика
            $task->truck->update([
                'status' => match($task->task_type) {
                    TruckPlannedTask::TYPE_FUELING => Truck::STATUS_FUELING,
                    TruckPlannedTask::TYPE_MAINTENANCE => Truck::STATUS_MAINTENANCE,
                    default => Truck::STATUS_MAINTENANCE,
                },
            ]);
        });
    }

    /**
     * Переместить грузовик в конец очереди (при поломке)
     */
    public function moveToQueueEnd(Truck $truck): void
    {
        // Получаем все незавершённые задачи грузовика
        $tasks = TruckPlannedTask::where('truck_id', $truck->id)
            ->where('completed', false)
            ->get();

        foreach ($tasks as $task) {
            if ($task->started_at) {
                // Если задача уже начата, освобождаем пост
                $post = $task->servicePost;
                if ($post) {
                    $post->release();
                }
                $task->update(['started_at' => null, 'service_post_id' => null]);
            }

            // Перемещаем в конец очереди
            $maxPosition = TruckPlannedTask::where('task_type', $task->task_type)
                ->where('completed', false)
                ->max('queue_position') ?? 0;

            $task->update(['queue_position' => $maxPosition + 1]);
        }
    }

    /**
     * Получить прогноз времени для очереди
     */
    public function getTimeForecast(TruckPlannedTask $task): ?string
    {
        if ($task->started_at) {
            // Уже в работе
            $post = $task->servicePost;
            if ($post) {
                $remaining = $post->getMinutesUntilFree();
                if ($remaining !== null) {
                    return $this->formatTimeRemaining($remaining);
                }
            }
            return 'В работе';
        }

        // В очереди - считаем время до начала
        $position = $task->queue_position;
        if ($position <= 0) {
            return null;
        }

        $postType = $this->getPostTypeForTask($task);
        $postsCount = ServicePost::getActivePostsCount($postType);

        if ($postsCount <= 0) {
            return 'Нет доступных постов';
        }

        // Считаем время ожидания на основе задач перед нами
        $tasksAhead = TruckPlannedTask::where('task_type', $task->task_type)
            ->where('completed', false)
            ->where('queue_position', '>', 0)
            ->where('queue_position', '<', $position)
            ->get();

        $totalMinutes = 0;
        foreach ($tasksAhead as $aheadTask) {
            $totalMinutes += $aheadTask->getDuration();
        }

        // Добавляем время от текущих задач на постах
        $occupiedPosts = ServicePost::ofType($postType)->active()->occupied()->get();
        foreach ($occupiedPosts as $post) {
            $remaining = $post->getMinutesUntilFree();
            if ($remaining !== null) {
                $totalMinutes += $remaining;
            }
        }

        // Делим на количество постов
        $waitMinutes = (int) ceil($totalMinutes / max(1, $postsCount));

        return $this->formatTimeRemaining($waitMinutes);
    }

    /**
     * Получить тип поста для задачи
     */
    protected function getPostTypeForTask(TruckPlannedTask $task): string
    {
        return match($task->task_type) {
            TruckPlannedTask::TYPE_FUELING => ServicePost::TYPE_FUELING,
            TruckPlannedTask::TYPE_MAINTENANCE => ServicePost::TYPE_MAINTENANCE,
            TruckPlannedTask::TYPE_TIRE_INFLATION,
            TruckPlannedTask::TYPE_WHEEL_TIGHTENING => ServicePost::TYPE_TIRE_SERVICE,
            default => ServicePost::TYPE_MAINTENANCE,
        };
    }

    /**
     * Форматировать время
     */
    protected function formatTimeRemaining(int $minutes): string
    {
        if ($minutes < 60) {
            return "{$minutes} мин";
        }

        $hours = (int) floor($minutes / 60);
        $mins = $minutes % 60;

        if ($mins === 0) {
            return "{$hours} ч";
        }

        return "{$hours} ч {$mins} мин";
    }

    /**
     * Получить информацию о запланированных задачах для грузовика
     */
    public function getPlannedTasksInfo(Truck $truck): array
    {
        $tasks = TruckPlannedTask::where('truck_id', $truck->id)
            ->where('completed', false)
            ->orderBy('queue_position')
            ->get();

        $info = [];
        foreach ($tasks as $task) {
            $info[] = [
                'type' => $task->getTypeLabel(),
                'queue_position' => $task->queue_position,
                'forecast' => $this->getTimeForecast($task),
                'started' => $task->started_at !== null,
            ];
        }

        return $info;
    }

    /**
     * Обновить очереди после завершения задачи
     */
    public function updateQueuesAfterCompletion(TruckPlannedTask $completedTask): void
    {
        $taskType = $completedTask->task_type;

        // Обновляем позиции в очереди
        $tasks = TruckPlannedTask::where('task_type', $taskType)
            ->where('completed', false)
            ->whereNull('started_at')
            ->orderBy('queue_position')
            ->get();

        $position = 1;
        foreach ($tasks as $task) {
            $task->update(['queue_position' => $position++]);
        }

        // Пытаемся начать следующую задачу
        $this->assignQueuesAndStart();
    }
}
