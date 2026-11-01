<?php

namespace App\Livewire\Traits;

use App\Models\Truck;
use App\Models\Miner;
use App\Models\Dump;
use App\Models\Zone;
use App\Models\Rock;
use App\Models\TruckTrip;
use App\Models\MiningOrder;
use App\Models\TripPause;
use App\Models\SystemSetting;
use App\Models\ServicePost;
use App\Models\TruckPlannedTask;
use App\Events\DriverRouteUpdated;
use App\Events\ExcavatorNotification;
use App\Services\RouteAssignmentService;
use App\Services\RouteOptimizerService;
use App\Services\MinerStatusService;
use App\Services\ZoneStatusService;
use App\Services\ServiceSchedulingService;
use App\Services\ShiftPlanningService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;

trait WithSettings
{
    public function loadThresholds(): void
    {
        $thresholds = SystemSetting::getOverloadThresholds();
        $this->minerThreshold = $thresholds['miner_threshold'];
        $this->zoneThreshold = $thresholds['zone_threshold'];
    }

    /**
     * Автосохранение при изменении ползунка забоя
     */
    public function updatedMinerThreshold(int $value): void
    {
        $this->saveMinerThreshold($value);
    }

    /**
     * Сохранить порог забоя
     */
    private function saveMinerThreshold(int $value): void
    {
        if ($value < 1 || $value > 20) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Порог должен быть от 1 до 20']);
            return;
        }

        SystemSetting::setMinerOverloadThreshold($value);

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => "Порог забоя установлен: {$value}",
        ]);
    }

    /**
     * Автосохранение при изменении ползунка зоны
     */
    public function updatedZoneThreshold(int $value): void
    {
        $this->saveZoneThreshold($value);
    }

    /**
     * Сохранить пороги ожидания (забой + зона) — кнопкой из UI.
     * Заменяет прежнее автосохранение через wire:model.live.
     */
    public function saveThresholds(): void
    {
        if ($this->minerThreshold < 1 || $this->minerThreshold > 20) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Порог ожидания на забое: от 1 до 20']);
            return;
        }
        if ($this->zoneThreshold < 1 || $this->zoneThreshold > 20) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Порог ожидания в зоне: от 1 до 20']);
            return;
        }

        SystemSetting::setMinerOverloadThreshold($this->minerThreshold);
        SystemSetting::setZoneOverloadThreshold($this->zoneThreshold);

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => "Пороги перегруженности сохранены: забой {$this->minerThreshold}, зона {$this->zoneThreshold}",
        ]);
    }

    /**
     * Сохранить порог среднего расстояния перевозки — кнопкой из UI.
     * Шаг до сотых (0.01).
     */
    public function saveAvgDistanceThreshold(): void
    {
        if ($this->avgDistanceThreshold < 0.01 || $this->avgDistanceThreshold > 50.0) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Порог среднего расстояния: от 0.01 до 50 км']);
            return;
        }

        SystemSetting::setAvgDistanceThreshold($this->avgDistanceThreshold);

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => "Порог среднего расстояния установлен: {$this->avgDistanceThreshold} км",
        ]);
    }

    /**
     * Сохранить порог зоны
     */
    private function saveZoneThreshold(int $value): void
    {
        if ($value < 1 || $value > 20) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Порог должен быть от 1 до 20']);
            return;
        }

        SystemSetting::setZoneOverloadThreshold($value);

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => "Порог зоны установлен: {$value}",
        ]);
    }

    /**
     * Загрузить настройки сервисных постов
     */
    public function loadServicePostSettings(): void
    {
        $settings = SystemSetting::getServicePostsSettings();
        $this->fuelingPostsCount = $settings['fueling_posts_count'];
        $this->maintenancePostsCount = $settings['maintenance_posts_count'];
        $this->tireServicePostsCount = $settings['tire_service_posts_count'];
        $this->to1IntervalHours = $settings['to1_interval_hours'];
        $this->to2IntervalHours = $settings['to2_interval_hours'];
        $this->emptyRunCoefficient = $settings['empty_run_coefficient'];
    }

    /**
     * Получить статус сервисных постов
     */
    public function getServicePostsStatusProperty(): array
    {
        $posts = ServicePost::with('currentTruck')->get();

        $result = [
            'fueling' => [],
            'maintenance' => [],
            'tire_service' => [],
        ];

        foreach ($posts as $post) {
            $result[$post->type][] = [
                'id' => $post->id,
                'name' => $post->name,
                'is_occupied' => $post->is_occupied,
                'truck' => $post->currentTruck?->number,
                'minutes_until_free' => $post->getMinutesUntilFree(),
            ];
        }

        return $result;
    }

    /**
     * Получить очередь на обслуживание
     */
    public function getServiceQueueProperty(): array
    {
        $tasks = TruckPlannedTask::with('truck')
            ->where('completed', false)
            ->orderBy('queue_position')
            ->get();

        $result = [
            'fueling' => [],
            'maintenance' => [],
            'tire_inflation' => [],
            'wheel_tightening' => [],
        ];

        foreach ($tasks as $task) {
            if (!isset($result[$task->task_type])) {
                continue;
            }

            $result[$task->task_type][] = [
                'id' => $task->id,
                'truck' => $task->truck?->number,
                'position' => $task->queue_position,
                'started_at' => $task->started_at?->format('H:i'),
                'duration' => $task->getDuration(),
                'to_type' => $task->to_type,
            ];
        }

        return $result;
    }

    /**
     * Автосохранение количества постов заправки
     */
    public function updatedFuelingPostsCount(int $value): void
    {
        $this->saveServicePostCount('fueling', $value);
    }

    /**
     * Автосохранение количества постов ТО
     */
    public function updatedMaintenancePostsCount(int $value): void
    {
        $this->saveServicePostCount('maintenance', $value);
    }

    /**
     * Автосохранение количества постов шиномонтажа
     */
    public function updatedTireServicePostsCount(int $value): void
    {
        $this->saveServicePostCount('tire_service', $value);
    }

    /**
     * Сохранить количество постов
     */
    private function saveServicePostCount(string $type, int $value): void
    {
        if ($value < 1 || $value > 10) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Количество постов должно быть от 1 до 10']);
            return;
        }

        match($type) {
            'fueling' => SystemSetting::setFuelingPostsCount($value),
            'maintenance' => SystemSetting::setMaintenancePostsCount($value),
            'tire_service' => SystemSetting::setTireServicePostsCount($value),
        };

        // Синхронизируем посты в базе
        $this->syncServicePosts();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => "Количество постов обновлено: {$value}",
        ]);
    }

    /**
     * Синхронизировать посты в базе с настройками
     */
    private function syncServicePosts(): void
    {
        // Убедимся что количество постов соответствует настройкам
        $types = [
            'fueling' => ['type' => ServicePost::TYPE_FUELING, 'count' => $this->fuelingPostsCount],
            'maintenance' => ['type' => ServicePost::TYPE_MAINTENANCE, 'count' => $this->maintenancePostsCount],
            'tire_service' => ['type' => ServicePost::TYPE_TIRE_SERVICE, 'count' => $this->tireServicePostsCount],
        ];

        foreach ($types as $data) {
            $existing = ServicePost::ofType($data['type'])->count();
            $needed = $data['count'];

            if ($existing < $needed) {
                // Добавляем недостающие посты
                for ($i = $existing + 1; $i <= $needed; $i++) {
                    ServicePost::create([
                        'type' => $data['type'],
                        'name' => match($data['type']) {
                            ServicePost::TYPE_FUELING => "Заправка {$i}",
                            ServicePost::TYPE_MAINTENANCE => "ТО {$i}",
                            ServicePost::TYPE_TIRE_SERVICE => "Шиномонтаж {$i}",
                        },
                    ]);
                }
            }
        }
    }

    /**
     * Отправить грузовик на обслуживание
     */
    public function sendToService(int $truckId, string $taskType, ?string $toType = null): void
    {
        $truck = Truck::find($truckId);
        if (!$truck) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Грузовик не найден']);
            return;
        }

        $service = app(ServiceSchedulingService::class);
        $task = $service->createTask($truck, $taskType, $toType);

        // Если есть свободный пост - сразу начинаем
        if ($service->hasFreePost($taskType)) {
            $service->startService($task);
        }

        $this->loadData();
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => "Грузовик {$truck->number} отправлен на {$task->getTypeLabel()}",
        ]);
    }

    /**
     * Завершить обслуживание.
     *
     * После завершения ТО статус грузовика меняется в БД на STATUS_FREE,
     * а ServiceSchedulingService::completeService() бродкастит
     * DispatcherNotification на канал 'dispatcher'. Существующий JS-слушатель
     * Echo.channel('dispatcher').listen('.truck-updated') ловит событие и
     * дёргает refresh-dispatcher-data → loadData() → статус самосвала
     * обновляется в реальном времени во вкладке «Самосвалы» у всех
     * открытых панелей диспетчера.
     */
    public function completeServiceTask(int $taskId): void
    {
        $task = TruckPlannedTask::find($taskId);
        if (!$task) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Задача не найдена']);
            return;
        }

        $service = app(ServiceSchedulingService::class);
        $service->completeService($task);

        $this->loadData();
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => "Обслуживание завершено",
        ]);
    }

    /**
     * Отменить задачу из очереди
     */
    public function cancelServiceTask(int $taskId): void
    {
        $task = TruckPlannedTask::find($taskId);
        if (!$task) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Задача не найдена']);
            return;
        }

        $service = app(ServiceSchedulingService::class);
        $service->cancelTask($task);

        $this->loadData();
        $this->dispatch('notify', [
            'type' => 'info',
            'message' => "Задача отменена",
        ]);
    }

    // =========================================
    // ПЛАНИРОВАНИЕ ОБСЛУЖИВАНИЯ НА СМЕНУ
    // =========================================

    /**
     * Запустить планирование обслуживания на смену
     */
    public function runShiftPlanning(): void
    {
        try {
            $result = $this->planningService->planShift();

            $this->loadData();

            $message = "Запланировано: {$result['planned']} задач";
            if ($result['queued'] > 0) {
                $message .= ", в очереди: {$result['queued']}";
            }

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => $message,
            ]);

        } catch (\Exception $e) {
            Log::error('Shift planning error', ['error' => $e->getMessage()]);
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Ошибка планирования: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Статистика очередей обслуживания
     */
    public function getQueueStatsProperty(): array
    {
        return [
            'fueling' => [
                'waiting' => TruckPlannedTask::pending()
                    ->where('task_type', TruckPlannedTask::TYPE_FUELING)
                    ->whereNull('started_at')
                    ->count(),
                'in_progress' => TruckPlannedTask::pending()
                    ->where('task_type', TruckPlannedTask::TYPE_FUELING)
                    ->whereNotNull('started_at')
                    ->count(),
            ],
            'maintenance' => [
                'waiting' => TruckPlannedTask::pending()
                    ->where('task_type', TruckPlannedTask::TYPE_MAINTENANCE)
                    ->whereNull('started_at')
                    ->count(),
                'in_progress' => TruckPlannedTask::pending()
                    ->where('task_type', TruckPlannedTask::TYPE_MAINTENANCE)
                    ->whereNotNull('started_at')
                    ->count(),
            ],
            'tire_service' => [
                'waiting' => TruckPlannedTask::pending()
                    ->whereIn('task_type', [TruckPlannedTask::TYPE_TIRE_INFLATION, TruckPlannedTask::TYPE_WHEEL_TIGHTENING])
                    ->whereNull('started_at')
                    ->count(),
                'in_progress' => TruckPlannedTask::pending()
                    ->whereIn('task_type', [TruckPlannedTask::TYPE_TIRE_INFLATION, TruckPlannedTask::TYPE_WHEEL_TIGHTENING])
                    ->whereNotNull('started_at')
                    ->count(),
            ],
        ];
    }

    /**
     * Получить информацию о запланированных задачах для грузовика
     */
    public function getPlannedTasksForTruck(int $truckId): array
    {
        $truck = $this->trucks->firstWhere('id', $truckId);
        if (!$truck) {
            return [];
        }

        return $this->planningService->getPlannedTasksInfo($truck);
    }
}
