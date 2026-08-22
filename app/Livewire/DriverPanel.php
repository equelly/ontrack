<?php

namespace App\Livewire;

use App\Domain\TruckStatus;
use App\Models\MiningOrder;
use App\Models\Truck;
use App\Models\TruckTrip;
use App\Models\TripPause;
use App\Models\Rock;
use App\Models\Zone;
use App\Models\TruckPlannedTask;
use App\Services\TruckStatusService;
use App\Services\RouteAssignmentService;
use App\Services\ServiceSchedulingService;
use App\Services\ShiftPlanningService;
use App\Exceptions\NoRouteAvailableException;
use App\Domain\RouteBlockReason;
use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Locked; // для сохранения объектов в Livewire без превращения в массивы 


#[Layout('components.layouts.app')]
#[Title('Панель водителя')]

class DriverPanel extends Component
{
    #[Locked] // Блокирует внутреннюю магию и защищает объект от превращения в массив
    public ?Truck $truck = null;
    public ?int $selectedTruckId = null;
    public array $trucks = [];
    public ?TruckTrip $currentTrip = null;
    public ?TripPause $activePause = null;
    public array $stats = [
        'shift_name' => '-',
        'total_trips' => 0,
        'today_trips' => 0,
        'today_volume' => 0,
        'avg_speed' => '-',
    ];
    public $newLoadCapacity;
    public string $statusColor = 'secondary';
    public string $statusLabel = '';
    //данные для заявок
    public $contentSearch;
    public $categoryId;
    public $userId;
    public $createdAt;
    public $mashineId;
    public $isSearchingRoute = false;
    public ?string $lastBlockReason = null; // последняя причина отказа (для дедупликации уведомлений)

    // Данные для таймера
    public ?string $tripStartedAt = null;
    public ?string $pauseStartedAt = null;
    public ?string $pauseType = null;
    public int $totalPauseSeconds = 0;

    // Топливо
    public $addedFuel;

    // Модальные окна
    public bool $showZoneModal = false;
    public bool $showDelayModal = false;
    public bool $showServiceModal = false;
    public string $delayReason = 'traffic';
    public int $delayMinutes = 15;
    public $availableZones = [];

    // Запрос на обслуживание
    public ?string $serviceType = null;
    public array $pendingServiceTasks = [];
    public array $serviceStats = [
        'mileage_since_fuel' => 0,
        'moto_hours_since_to' => 0,
        'fueling_threshold' => 0,
        'next_to_type' => 'TO-1',
    ];

    // Текущее обслуживание
    public ?array $currentServiceTask = null;
    // Запланированное обслуживание на смену
    public array $plannedShiftServices = [];


    public function mount(): void
    {
        $this->rocks = Rock::all()->toArray();
        $this->loadTrucks();
        
        // Try to get truck from session first
        $savedTruckId = session('selected_truck_id');
        if ($savedTruckId) {
            $this->selectedTruckId = (int) $savedTruckId;
            $this->selectTruck();
        } elseif (auth()->user()->truck_id) {
            // If no cookie, use the truck assigned to the user
            $this->selectedTruckId = auth()->user()->truck_id;
            $this->selectTruck();
        }
    }

    protected function loadTrucks(): void
    {
        $user = auth()->user();
        $driverId = $user->id;

        // В режиме разработки показываем все грузовики
        $allTrucks = Truck::with('driver')->orderBy('number')->get();

        $this->trucks = $allTrucks->map(function ($t) use ($driverId) {
            $isMine = $t->driver_id === $driverId;
            $isBreakdown = $t->status === 'breakdown';
            $isFree = in_array($t->status, ['free', 'completed', 'breakdown']) || !$t->driver_id;

            return [
                'id' => $t->id,
                'number' => $t->number,
                'is_mine' => $isMine,
                'is_breakdown' => $isBreakdown,
                'is_free' => $isFree || $isMine,
                'driver_name' => $t->driver?->name,
            ];
        })->toArray();
    }

    public function selectTruck(): void
    {
        if (!$this->selectedTruckId) {
            return;
        }

        $this->truck = Truck::find($this->selectedTruckId);
        $this->newLoadCapacity = $this->truck->load_capacity;
        if ($this->truck) {
            // Привязываем грузовик к водителю если нужно
            if ($this->truck->driver_id !== auth()->id()) {
                $this->truck->update(['driver_id' => auth()->id()]);
            }
            $this->loadData();
            // Временно отключено - проверка запланированного обслуживания
            // $this->checkScheduledService();
            
            // Сохраняем в сессию
            session()->put('selected_truck_id', $this->selectedTruckId);

            $this->dispatch('truck-selected', ['truck_id' => $this->truck->id]);
        }
    }

    /**
     * Проверить запланированное обслуживание при начале смены
     */
    protected function checkScheduledService(): void
    {
        if (!$this->truck) {
            return;
        }

        $service = app(ServiceSchedulingService::class);

        // Планируем обслуживание на смену (если ещё не запланировано)
        $plannedTasks = $service->planServiceForShift($this->truck);

        // Показываем уведомление о запланированных задачах, но НЕ отправляем автоматически
        if (!empty($plannedTasks)) {
            $messages = [];
            foreach ($plannedTasks as $taskInfo) {
                $messages[] = $taskInfo['reason'];
            }
            
            $this->dispatch('notify', [
                'type' => 'info',
                'message' => 'Запланировано: ' . implode(', ', $messages),
            ]);
        }
    }

    protected function loadData(): void
    {
        if (!$this->truck) {
            return;
        }

        $this->truck->refresh();

        $this->currentTrip = TruckTrip::where('truck_id', $this->truck->id)
            ->whereNull('completed_at')
            ->with(['miner.rocks', 'dump', 'zone.rocks', 'miningOrder.rock', 'rock', 'pauses'])
            ->latest()
            ->first();

        // Находим активную паузу
        $this->activePause = null;
        if ($this->currentTrip) {
            $this->activePause = $this->currentTrip->pauses
                ->whereNull('ended_at')
                ->first();
        }

        // Данные для таймера
        $this->tripStartedAt = $this->currentTrip?->started_at?->toIso8601String();
        $this->pauseStartedAt = $this->activePause?->started_at?->toIso8601String();
        $this->pauseType = $this->activePause?->type;

        // Считаем общее время пауз (завершённые + текущая)
        $this->totalPauseSeconds = $this->currentTrip?->getTotalPauseSeconds() ?? 0;

        $this->statusColor = TruckStatus::color($this->truck->status);
        $this->statusLabel = TruckStatus::label($this->truck->status);

        $this->stats = [
            'shift_name' => $this->getShiftName(),
            'total_trips' => TruckTrip::where('truck_id', $this->truck->id)
                ->whereNotNull('completed_at')
                ->count(),
            'today_trips' => TruckTrip::where('truck_id', $this->truck->id)
                ->whereNotNull('completed_at')
                ->whereDate('completed_at', today())
                ->count(),
            'today_volume' => (float) TruckTrip::where('truck_id', $this->truck->id)
                ->whereNotNull('completed_at')
                ->whereDate('completed_at', today())
                ->sum('load_volume'),
            'avg_speed' => $this->calculateAverageSpeed() ?? '-',
        ];

        // Отправляем событие для перезапуска таймера
        $this->dispatch('restart-timer');

        // Загружаем данные по обслуживанию
        $this->loadServiceStats();

        // Загружаем текущую задачу обслуживания
        $this->loadCurrentServiceTask();
        
        // Загружаем запланированные ТО и заправку на смену
        $this->loadPlannedShiftServices();

    }

    /**
     * Загрузить текущую задачу обслуживания
     */
    protected function loadCurrentServiceTask(): void
    {
        if (!$this->truck) {
            $this->currentServiceTask = null;
            return;
        }

        $task = $this->truck->currentServiceTask()->with('servicePost')->first();

        if ($task) {
            $this->currentServiceTask = [
                'id' => $task->id,
                'type' => $task->getTypeLabel(),
                'post_name' => $task->servicePost?->name,
                'started_at' => $task->started_at?->format('H:i'),
                'duration' => $task->getDuration(),
            ];
        } else {
            $this->currentServiceTask = null;
        }
    }

    /**
     * Загрузить запланированные ТО и заправку на текущую смену
     */
    protected function loadPlannedShiftServices(): void
    {
        if (!$this->truck) {
            $this->plannedShiftServices = [];
            return;
        }

        $shiftPlanningService = app(ShiftPlanningService::class);
        $this->plannedShiftServices = $shiftPlanningService->getPlannedTasksInfo($this->truck);

    }

    protected function loadServiceStats(): void
    {
        if (!$this->truck) {
            return;
        }

        $service = app(ServiceSchedulingService::class);

        $this->serviceStats = [
            'mileage_since_fuel' => $this->truck->mileage_since_fuel ?? 0,
            'moto_hours_since_to' => round(($this->truck->moto_minutes_since_to ?? 0) / 60, 1),
            'fueling_threshold' => $service->calculateFuelingThreshold($this->truck),
            'next_to_type' => $service->getNextTOType($this->truck),
        ];

        // Загружаем запланированные задачи
        $this->pendingServiceTasks = $service->getPendingTasks($this->truck)
            ->map(fn($task) => [
                'id' => $task->id,
                'type' => $task->getTypeLabel(),
                'queue_position' => $task->queue_position,
                'started_at' => $task->started_at?->format('H:i'),
                'duration' => $task->getDuration(),
                'post_name' => $task->servicePost?->name,
            ])
            ->toArray();
    }

    protected function getShiftName(): string
    {
        $hour = now()->hour;
        $minute = now()->minute;
        
        // Дневная смена: 7:30 - 19:30
        // Ночная смена: 19:30 - 7:30
        
        // Проверяем дневную смену
        if ($hour > 7 && $hour < 19) {
            return 'Дневная (7:30-19:30)';
        }
        if ($hour === 7 && $minute >= 30) {
            return 'Дневная (7:30-19:30)';
        }
        if ($hour === 19 && $minute < 30) {
            return 'Дневная (7:30-19:30)';
        }
        
        // Остальное - ночная смена
        return 'Ночная (19:30-7:30)';
    }

    // =========================================
    // ДЕЙСТВИЯ ВОДИТЕЛЯ
    // =========================================

    public function assignRoute(): void
    {
        try {
            $routeService = app(RouteAssignmentService::class);
            $routeService->assignForTruck($this->truck);
            
            // Маршрут найден! Выключаем поиск.
            $this->isSearchingRoute = false;
            $this->loadData();

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Маршрут успешно назначен!',
            ]);

        } catch (NoRouteAvailableException $e) {
            // Точная причина, почему маршрута нет
            $wasSearching = $this->isSearchingRoute;
            $this->isSearchingRoute = true;
            
            // Формируем человекочитаемое сообщение для водителя
            $message = $this->buildDriverMessage($e->getDiagnostics());
            
            // Выводим уведомление только один раз (при первом переходе в режим ожидания)
            // или если причина изменилась
            if (!$wasSearching || ($this->lastBlockReason ?? '') !== $message) {
                $this->lastBlockReason = $message;
                $this->dispatch('notify', [
                    'type' => 'warning',
                    'message' => $message,
                ]);
            }
        } catch (\Exception $e) {
            // Системная ошибка (грузовик занят, БД недоступна и т.д.)
            $this->isSearchingRoute = false;
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Построить человекочитаемое сообщение для водителя из диагностики.
     *
     * Вместо абстрактного "Свободных маршрутов нет" показываем:
     * "Маршрутов нет: <причина>. <Что нужно сделать>"
     */
    private function buildDriverMessage(array $diagnostics): string
    {
        $primaryReason = $diagnostics['primary_reason'] ?? null;
        $summary = $diagnostics['summary'] ?? [];

        if (!$primaryReason) {
            return 'Свободных маршрутов нет. Ожидание доступности...';
        }

        $label = RouteBlockReason::label($primaryReason);
        $action = RouteBlockReason::action($primaryReason);
        $count = $summary[$primaryReason] ?? 0;

        // Пример вывода:
        // "Маршрутов нет: 8 забоев не работают. Дождитесь, пока мастер активирует забои."
        $prefix = match($primaryReason) {
            RouteBlockReason::MINER_NOT_WORKING    => $count . ' ' . $this->pluralize($count, ['забой не работает', 'забоя не работают', 'забоев не работают']),
            RouteBlockReason::MINER_INACTIVE       => $count . ' ' . $this->pluralize($count, ['забой выключен', 'забоя выключено', 'забоев выключено']),
            RouteBlockReason::NO_AVAILABLE_ZONES   => 'нет доступных зон разгрузки (на ' . $count . ' ' . $this->pluralize($count, ['маршруте', 'маршрутах', 'маршрутах']) . ')',
            RouteBlockReason::NO_CURRENT_ROCK      => $count . ' ' . $this->pluralize($count, ['забое не задана порода', 'забоях не задана порода', 'забоях не задана порода']),
            RouteBlockReason::MINER_OVERLOADED     => $count . ' ' . $this->pluralize($count, ['забой перегружен', 'забоя перегружены', 'забоев перегружено']),
            RouteBlockReason::ROCK_RESTRICTED      => 'порода запрещена для этого самосвала',
            RouteBlockReason::NO_ACTIVE_ORDERS     => 'нет активных маршрутов',
            default                                => $label,
        };

        return "Маршрутов нет: {$prefix}. {$action}";
    }

    /**
     * Склонение существительных по числу (1 забой / 2 забоя / 5 забоев).
     */
    private function pluralize(int $count, array $forms): string
    {
        $n = abs($count) % 100;
        $n1 = $n % 10;
        if ($n > 10 && $n < 20) return $forms[2];
        if ($n1 > 1 && $n1 < 5) return $forms[1];
        if ($n1 == 1) return $forms[0];
        return $forms[2];
    }

    // Слушатель WebSocket-события из канала 'routes'
    #[On('echo:routes,RoutesUpdated')]
    public function onRoutesUpdated()
    {
        // Если водитель в режиме ожидания, пробуем назначить маршрут снова!
        if ($this->isSearchingRoute) {
            $this->assignRoute();
        }
    }

    // Ручная отмена ожидания водителем
    public function stopSearchingRoute(): void
    {
        $this->isSearchingRoute = false;
        $this->dispatch('notify', [
            'type' => 'info',
            'message' => 'Ожидание маршрута отменено.',
        ]);
    }

    public function startLoading(): void
    {
        try {
            $statusService = app(TruckStatusService::class);
            $statusService->changeStatus($this->truck, 'loading');
            $this->loadData();

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Вы прибыли на погрузку. Ожидайте.',
            ]);
        } catch (\Exception $e) {
            Log::error('Start loading failed', ['error' => $e->getMessage()]);
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function startUnloading(): void
    {
        try {
            $statusService = app(TruckStatusService::class);
            $statusService->changeStatus($this->truck, 'unloading');
            $this->loadData();

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Прибыл на выгрузку',
            ]);
        } catch (\Exception $e) {
            Log::error('Start unloading failed', ['error' => $e->getMessage()]);
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function completeTrip(): void
    {
        try {
            // 1. РАСЧЕТ И СПИСАНИЕ ТОПЛИВА ЗА РЕЙС
            if ($this->currentTrip && $this->truck && $this->truck->truckModel) {
                // Получаем расстояние из заявки (маршрута)
                $distance = (float) ($this->currentTrip->miningOrder->distance_km ?? 0);
                
                // Учитываем холостой пробег (коэф 0.5, итого 1.5)
                $totalDistance = $distance * 1.5;
                
                // Получаем расход топлива на 100 км (из модели грузовика)
                $fuelConsumption = (float) ($this->truck->truckModel->fuel_consumption ?? 0);
                
                if ($totalDistance > 0 && $fuelConsumption > 0) {
                    // Считаем израсходованные литры
                    $fuelUsed = ($totalDistance / 100) * $fuelConsumption;
                    
                    // Вычитаем из текущего уровня, не опускаемся ниже нуля
                    $this->truck->fuel_level = max(0, $this->truck->fuel_level - $fuelUsed);
                    $this->truck->save();
                }
            }

            // 2. СМЕНА СТАТУСА (Завершение рейса)
            $statusService = app(TruckStatusService::class);
            $statusService->changeStatus($this->truck, 'completed');
            
            // 3. ОБНОВЛЕНИЕ ДАННЫХ ПАНЕЛИ
            $this->loadData();

            // Проверяем, есть ли запланированные задачи обслуживания
            $this->checkPendingServiceTasks();

            // Уведомляем водителя
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Рейс завершён. Топливо списано.',
            ]);

        } catch (\Exception $e) {
            Log::error('Complete trip failed', ['error' => $e->getMessage()]);
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Проверить запланированные задачи обслуживания после завершения рейса
     */
    protected function checkPendingServiceTasks(): void
    {
        if (!$this->truck) {
            return;
        }

        $service = app(ServiceSchedulingService::class);

        // Получаем все незавершённые задачи без started_at (в очереди)
        $pendingTasks = TruckPlannedTask::where('truck_id', $this->truck->id)
            ->where('completed', false)
            ->whereNull('started_at')
            ->orderBy('queue_position')
            ->get();

        if ($pendingTasks->isEmpty()) {
            return;
        }

        // Берём первую задачу
        $nextTask = $pendingTasks->first();

        // Проверяем наличие свободного поста
        $freePost = $service->getFreePosts($nextTask->task_type)->first();

        if ($freePost) {
            // Есть свободный пост - отправляем на обслуживание
            $freePost->occupy($this->truck, $nextTask->getDuration());
            $nextTask->start($freePost->id);

            // Определяем статус
            $newStatus = match($nextTask->task_type) {
                TruckPlannedTask::TYPE_FUELING => 'fueling',
                TruckPlannedTask::TYPE_MAINTENANCE => 'maintenance',
                default => 'service',
            };

            $this->truck->update(['status' => $newStatus]);
            $this->loadData();

            $taskLabel = $nextTask->getTypeLabel();
            $this->dispatch('notify', [
                'type' => 'warning',
                'message' => "Направление на {$taskLabel}. Пост: {$freePost->name}",
            ]);
        } else {
            // Посты заняты - уведомляем о позиции в очереди
            $taskLabel = $nextTask->getTypeLabel();
            $position = $nextTask->queue_position;
            $this->dispatch('notify', [
                'type' => 'info',
                'message' => "Запланировано: {$taskLabel}. Очередь: позиция {$position}",
            ]);
        }
    }

    public function goToStandby(): void
    {
        try {
            $statusService = app(TruckStatusService::class);
            $statusService->changeStatus($this->truck, 'free');
            $this->loadData();

            $this->dispatch('notify', [
                'type' => 'info',
                'message' => 'Вы ушли в отстой.',
            ]);
        } catch (\Exception $e) {
            Log::error('Go to standby failed', ['error' => $e->getMessage()]);
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function reportBreakdown(): void
    {
        try {
            $statusService = app(TruckStatusService::class);
            $statusService->changeStatus($this->truck, 'breakdown');
            $this->loadData();

            $this->dispatch('notify', [
                'type' => 'warning',
                'message' => 'Поломка зарегистрирована. Маршрут сохранён.',
            ]);
        } catch (\Exception $e) {
            Log::error('Report breakdown failed', ['error' => $e->getMessage()]);
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function resolveBreakdownContinue(): void
    {
        try {
            $statusService = app(TruckStatusService::class);
            $statusService->resolveBreakdown($this->truck, true);
            $this->loadData();

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Поломка устранена. Продолжайте маршрут.',
            ]);
        } catch (\Exception $e) {
            Log::error('Breakdown continue failed', ['error' => $e->getMessage()]);
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function resolveBreakdownCancel(): void
    {
        try {
            $statusService = app(TruckStatusService::class);
            $statusService->resolveBreakdown($this->truck, false);
            $this->loadData();

            $this->dispatch('notify', [
                'type' => 'info',
                'message' => 'Рейс отменён. Получен новый маршрут.',
            ]);
        } catch (\Exception $e) {
            Log::error('Breakdown cancel failed', ['error' => $e->getMessage()]);
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function resumeFromDelay(): void
    {
        try {
            $statusService = app(TruckStatusService::class);
            $statusService->resumeFromDelay($this->truck);
            $this->loadData();

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Задержка окончена. Продолжайте маршрут.',
            ]);
        } catch (\Exception $e) {
            Log::error('Resume from delay failed', ['error' => $e->getMessage()]);
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    // =========================================
    // ЗАПРОСЫ НА ОБСЛУЖИВАНИЕ
    // =========================================

    public function openServiceModal(string $type): void
    {
        $this->serviceType = $type;
        $this->showServiceModal = true;
    }

    public function closeServiceModal(): void
    {
        $this->showServiceModal = false;
        $this->serviceType = null;
    }

    public function requestTireInflation(): void
    {
        $this->requestService(TruckPlannedTask::TYPE_TIRE_INFLATION);
    }

    public function requestWheelTightening(): void
    {
        $this->requestService(TruckPlannedTask::TYPE_WHEEL_TIGHTENING);
    }

    public function requestFueling(): void
    {
        $this->requestService(TruckPlannedTask::TYPE_FUELING);
    }

    protected function requestService(string $taskType): void
    {
        try {
            $service = app(ServiceSchedulingService::class);
            $result = $service->handleDriverRequest($this->truck, $taskType);

            $this->loadData();
            $this->showServiceModal = false;

            $type = $result['success'] ? 'success' : 'info';
            $this->dispatch('notify', [
                'type' => $type,
                'message' => $result['message'],
            ]);

        } catch (\Exception $e) {
            Log::error('Service request failed', ['error' => $e->getMessage()]);
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Ошибка: ' . $e->getMessage(),
            ]);
        }
    }

    public function cancelServiceTask(int $taskId): void
    {
        try {
            $task = TruckPlannedTask::where('id', $taskId)
                ->where('truck_id', $this->truck->id)
                ->firstOrFail();

            $service = app(ServiceSchedulingService::class);
            $service->cancelTask($task);

            $this->loadData();

            $this->dispatch('notify', [
                'type' => 'info',
                'message' => 'Заявка отменена',
            ]);

        } catch (\Exception $e) {
            Log::error('Cancel service task failed', ['error' => $e->getMessage()]);
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Ошибка: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Завершить текущее обслуживание
     */
    public function completeService(): void
    {
        try {
            $task = $this->truck->currentServiceTask()->first();

            if (!$task) {
                throw new \Exception('Нет активной задачи обслуживания');
            }

            $service = app(ServiceSchedulingService::class);
            $service->completeService($task);

            $this->loadData();

            // Проверяем, есть ли ещё задачи в очереди для этого грузовика
            $this->checkAndStartNextService();

        } catch (\Exception $e) {
            Log::error('Complete service failed', ['error' => $e->getMessage()]);
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Ошибка: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Проверить и начать следующее обслуживание из очереди
     */
    protected function checkAndStartNextService(): void
    {
        if (!$this->truck) {
            return;
        }

        $service = app(ServiceSchedulingService::class);

        // Получаем следующую задачу из очереди для этого грузовика
        $nextTask = TruckPlannedTask::where('truck_id', $this->truck->id)
            ->where('completed', false)
            ->whereNull('started_at')
            ->orderBy('queue_position')
            ->first();

        if (!$nextTask) {
            // Нет больше задач - грузовик свободен
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Обслуживание завершено! Запросите маршрут.',
            ]);
            return;
        }

        // Проверяем наличие свободного поста для следующей задачи
        $freePost = $service->getFreePosts($nextTask->task_type)->first();

        if ($freePost) {
            // Есть свободный пост - начинаем обслуживание
            $freePost->occupy($this->truck, $nextTask->getDuration());
            $nextTask->start($freePost->id);

            // Определяем статус
            $newStatus = match($nextTask->task_type) {
                TruckPlannedTask::TYPE_FUELING => 'fueling',
                TruckPlannedTask::TYPE_MAINTENANCE => 'maintenance',
                default => 'service',
            };

            $this->truck->update(['status' => $newStatus]);
            $this->loadData();

            $taskLabel = $nextTask->getTypeLabel();
            $this->dispatch('notify', [
                'type' => 'warning',
                'message' => "Следующее обслуживание: {$taskLabel}. Пост: {$freePost->name}",
            ]);
        } else {
            // Нет свободных постов - остаёмся в очереди
            $taskLabel = $nextTask->getTypeLabel();
            $position = $nextTask->queue_position;
            $this->dispatch('notify', [
                'type' => 'info',
                'message' => "Обслуживание завершено! {$taskLabel} в очереди, позиция: {$position}",
            ]);
        }
    }

    // =========================================
    // МОДАЛЬНЫЕ ОКНА
    // =========================================

    public function openZoneModal(): void
    {
        $this->loadAvailableZones();
        $this->showZoneModal = true;
    }

    public function closeZoneModal(): void
    {
        $this->showZoneModal = false;
    }

    public function openDelayModal(): void
    {
        $this->showDelayModal = true;
    }

    public function closeDelayModal(): void
    {
        $this->showDelayModal = false;
    }

    public function confirmDelay(): void
    {
        try {
            $statusService = app(TruckStatusService::class);
            $statusService->changeStatus($this->truck, 'delayed', [
                'reason' => $this->delayReason,
                'estimated_delay_minutes' => $this->delayMinutes,
            ]);

            $this->loadData();
            $this->showDelayModal = false;

            $this->dispatch('notify', [
                'type' => 'warning',
                'message' => 'Задержка зарегистрирована',
            ]);

        } catch (\Exception $e) {
            Log::error('Delay report failed', ['error' => $e->getMessage()]);
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function selectZone(int $zoneId): void
    {
        try {
            $routeService = app(RouteAssignmentService::class);
            $routeService->reassignZone($this->truck, $zoneId);

            $this->loadData();
            $this->showZoneModal = false;

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Зона изменена!',
            ]);

        } catch (\Exception $e) {
            Log::error('Zone selection failed', ['error' => $e->getMessage()]);
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    protected function loadAvailableZones(): void
    {
        if (!$this->currentTrip || !$this->currentTrip->miningOrder) {
            $this->availableZones = collect();
            return;
        }

        $rockId = $this->currentTrip->miningOrder->rock_id;
        $currentZoneId = $this->currentTrip->zone_id;

        $this->availableZones = Zone::where('delivery', true)
            ->whereHas('rocks', fn($q) => $q->where('rocks.id', $rockId))
            ->whereRaw('volume < capacity')
            ->where('id', '!=', $currentZoneId)
            ->with('dump', 'rocks')
            ->get()
            ->map(fn($zone) => [
                'id' => $zone->id,
                'name' => $zone->name_zone,
                'dump_name' => $zone->dump?->name_dump,
                'available_capacity' => $zone->capacity - $zone->volume,
            ]);
    }

    // =========================================
    // REAL-TIME EVENTS
    // =========================================

    #[On('route-updated')]
    public function onRouteUpdated(array $data): void
    {
        Log::info('Route updated event', $data);
        
        if ($this->truck && isset($data['truck_id']) && $data['truck_id'] === $this->truck->id) {
            $this->loadData();
            
            // Определяем сообщение по типу действия
            $action = $data['action'] ?? 'route_assigned';
            $message = match ($action) {
                'route_assigned' => 'Назначен новый маршрут!',
                'route_reassigned' => 'Маршрут изменён!',
                'route_completed' => 'Рейс завершён!',
                'zone_reassigned' => 'Зона изменена!',
                default => 'Данные обновлены',
            };
            
            $this->dispatch('notify', [
                'type' => $action === 'route_completed' ? 'success' : 'info',
                'message' => $message,
            ]);
        }
    }

    #[On('zone-changed')]
    public function onZoneChanged(): void
    {
        Log::info('Zone changed event');
        $this->loadData();
        $this->dispatch('notify', [
            'type' => 'info',
            'message' => 'Зона изменена!',
        ]);
    }

    #[On('loading-completed')]
    public function onLoadingCompleted(array $data): void
    {
        Log::info('Loading completed event', $data);
        $this->loadData();

        $message = 'Погрузка завершена! Можете отправляться.';
        if (isset($data['zone_changed']) && $data['zone_changed']) {
            $message = "Погрузка завершена. Место разгрузки изменено: {$data['new_dump']} - {$data['new_zone']}";
        }

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => $message,
        ]);
    }

    // =========================================
    // СТАТИСТИКА
    // =========================================

    /**
     * Рассчитать среднюю скорость за смену (км/ч)
     * Средняя скорость = общее расстояние / общее время перевозки
     * Время перевозки = только время в статусе transporting (без разгрузки)
     */
    protected function calculateAverageSpeed(): ?float
    {
        if (!$this->truck) {
            return null;
        }

        // Получаем завершённые рейсы за сегодня с данными о времени перевозки
        $trips = TruckTrip::where('truck_id', $this->truck->id)
            ->whereNotNull('completed_at')
            ->whereDate('completed_at', today())
            ->whereNotNull('loaded_at')
            ->whereNotNull('unloading_started_at')
            ->with('miningOrder')
            ->get();

        if ($trips->isEmpty()) {
            return null;
        }

        $totalDistance = 0;
        $totalTransportingHours = 0;

        foreach ($trips as $trip) {
            // Расстояние из miningOrder
            $distance = $trip->miningOrder?->distance_km ?? 0;
            $totalDistance += $distance;

            // Время перевозки в часах
            $totalTransportingHours += $trip->getTransportingHours();
        }

        if ($totalTransportingHours <= 0) {
            return null;
        }

        // Средняя скорость = расстояние / время
        return round($totalDistance / $totalTransportingHours, 1);
    }

    // =========================================
    // TRUCK RESTRICTIONS
    // =========================================

    public array $rocks = [];


    public function updateLoadCapacity()
    {
        $newCapacity = (float) $this->newLoadCapacity;
        $maxCapacity = $this->truck->truckModel->load_capacity ?? 9999;

        if ($newCapacity > $maxCapacity) {
            $this->dispatch('notify', ['type' => 'error', 'message' => "Превышен максимум: {$maxCapacity}"]);
            return;
        }

        // Прямой SQL-запрос, обходим Eloquent
        \DB::table('trucks')->where('id', $this->truck->id)->update(['load_capacity' => $newCapacity]);

        // Обновляем модель в памяти Livewire
        $this->truck->refresh();
        $this->newLoadCapacity = $this->truck->load_capacity;

        $this->dispatch('notify', ['type' => 'success', 'message' => 'Грузоподъемность обновлена']);
    }

    public function toggleRockRestriction($rockId): void
    {
        try {
            $exists = $this->truck->restrictions()
                ->where('rock_id', $rockId)
                ->exists();
                
            if ($exists) {
                $this->truck->restrictions()
                    ->where('rock_id', $rockId)
                    ->delete();
                $message = 'Ограничение снято';
            } else {
                \App\Models\TruckRestriction::create([
                    'truck_id' => $this->truck->id,
                    'rock_id' => $rockId
                ]);
                $message = 'Ограничение добавлено';
                $this->truck->refresh();
            }
            
            $this->truck->refresh();
            
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => $message,
            ]);
            
        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function getFuelStatsProperty()
    {
        if (!$this->truck) {
            return [
                'fuel_percent' => 0,
                'avg_distance' => 0,
                'total_trip_distance' => 0,
                'estimated_trips' => 0
            ];
        }
        // Подгружаем связь, если она была потеряна после refresh()
        $this->truck->load('truckModel');
        
        $fuelCapacity = $this->truck->truckModel->fuel_capacity ?? 0;
        
        // Защита от деления на ноль
        $fuel_percent = 0;
        if ($fuelCapacity > 0) {
            $fuel_percent = (($this->truck->fuel_level ?? 0) / $fuelCapacity) * 100;
        }

        // a) Расчет процента топлива
        $fuel_percent = ($this->truck->fuel_level / $this->truck->truckModel->fuel_capacity) * 100;

        // b) Среднее расстояние рейса
        $avg_distance = MiningOrder::where('active', true)
            ->whereNotNull('distance_km')
            ->average('distance_km') ?? 0;

        // c) Общее расстояние с учетом холостого хода
        $total_trip_distance = $avg_distance * 1.5;

        // d) Расчет количества рейсов
        $estimated_trips = 0;
        if ($avg_distance > 0 && $this->truck->truckModel->fuel_consumption > 0) {
            $trip_fuel_consumption = ($total_trip_distance / 100) * $this->truck->truckModel->fuel_consumption;
            $estimated_trips = $trip_fuel_consumption > 0 ? 
                floor($this->truck->fuel_level / $trip_fuel_consumption) : 0;
        }

        return [
            'fuel_percent' => $fuel_percent,
            'avg_distance' => $avg_distance,
            'total_trip_distance' => $total_trip_distance,
            'estimated_trips' => $estimated_trips
        ];
    }

    public function updateFuelLevel()
    {
        if (!$this->truck || $this->truck->status !== 'fueling') {
            return;
        }

        $this->validate([
            'addedFuel' => ['required', 'numeric', 'min:1', 
                'max:' . ($this->truck->truckModel->fuel_capacity - $this->truck->fuel_level)]
        ]);

        $this->truck->fuel_level += $this->addedFuel;
        $this->truck->save();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => "Добавлено {$this->addedFuel} литров. Текущий уровень: {$this->truck->fuel_level} л"
        ]);
            // Автоматически завершаем заправку и меняем статус грузовика
        $this->completeService();

        $this->addedFuel = null;
    }

    public function render()
    {
        $categories = \App\Models\Category::all();
        $users = \App\Models\User::orderBy('name')->get();
        $allMashines = \App\Models\Mashine::orderBy('number')->get();

        $mashinesQuery = \App\Models\Mashine::with(['orders' => function($q) {
            $q->where('content', '!=', '')
              ->when($this->contentSearch, function($q) {
                  $q->where('content', 'like', '%' . $this->contentSearch . '%');
              })
              ->when($this->categoryId, function($q) {
                  $q->where('category_id', $this->categoryId);
              })
              ->when($this->userId, function($q) {
                  $q->where('user_id', $this->userId);
              })
              ->when($this->createdAt, function($q) {
                  $q->whereDate('created_at', $this->createdAt);
              });
        }, 'sets']);

        // --- АВТОФИЛЬТР ПО ТЕКУЩЕМУ ГРУЗОВИКУ ---
        if ($this->truck && $this->truck->mashine_id) {
            $mashinesQuery->where('id', $this->truck->mashine_id);
        } else {
            // Если грузовик не выбран или не привязан к карточке оборудования
            $mashinesQuery->whereRaw('1=0');
        }

        $mashines = $mashinesQuery->get()->filter(function($mashine) {
            return $mashine->sets->isNotEmpty() || $mashine->orders->isNotEmpty();
        });

        $ordersCount = $mashines->sum(fn($m) => $m->orders->count());

        return view('livewire.driver-panel', compact('mashines', 'ordersCount', 'categories', 'users', 'allMashines'));
    }
}