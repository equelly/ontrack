<?php

namespace App\Livewire;

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
use App\Livewire\Traits\WithAlerts;
use App\Livewire\Traits\WithAnalytics;
use App\Livewire\Traits\WithMiners;
use App\Livewire\Traits\WithRoutes;
use App\Livewire\Traits\WithSettings;
use App\Livewire\Traits\WithTrucks;
use App\Livewire\Traits\WithZones;



#[Layout('components.layouts.app')]
#[Title('Панель диспетчера')]
class MainDispatcherPanel extends Component
{
    use WithAlerts, WithAnalytics, WithMiners, WithRoutes, WithSettings, WithTrucks, WithZones;

    // Dependency Injection сервисов
    protected RouteAssignmentService $routeService;
    protected RouteOptimizerService $optimizerService;
    protected ZoneStatusService $zoneStatusService;
    protected ShiftPlanningService $planningService;

    public $trucks;
    public $miners;
    public $dumps;
    public array $bermZoneIds = []; // ID зон под обваловкой — для отметки в таблице самосвалов
    public $zones;
    public $rocks;
    public $orders;
    public string $activeTab = 'trucksTab';
    public string $statsPeriod = 'shift';      // shift / today / week / month / year
    public string $statsShift = 'all';          // all / day / night
    public string $statsGrouping = 'by_miner';  // by_miner / by_truck / by_rock
    public ?int $statsMinerId = null;           // null = все экскаваторы

    // Фильтры аналитики проведённых ТО
    public string $maintenanceStatsPeriod = 'shift';      // shift / today / week / month / year
    public string $maintenanceStatsShift = 'all';          // all / day / night
    public string $maintenanceStatsGrouping = 'by_truck';  // by_truck / by_type / by_post / by_to_type
    public ?int $maintenanceStatsTruckId = null;           // null = все грузовики
    public string $maintenanceStatsTaskType = 'all';       // all / maintenance / fueling / tire_inflation / wheel_tightening

    // Назначение маршрута
    public ?int $selectedTruckId = null;
    public ?int $selectedMinerId = null;
    public ?int $selectedOrderId = null;
    public ?int $selectedZoneId = null;
    public $availableOrders = [];
    public $availableZones = [];
    public ?int $editOrderId = null;
    public ?int $editDumpId = null;
    public ?int $editRockId = null;
    public ?bool $editActive = null;
    public ?int $editWeight = null; // Вес маршрута
    public array $editDistances = []; // Расстояния от забоя до перегрузок

    // Создание нового маршрута
    public bool $showCreateOrderModal = false;
    public ?int $newOrderMinerId = null;
    public ?int $newOrderDumpId = null;
    public ?int $newOrderRockId = null;
    public ?float $newOrderDistanceKm = null;
    public int $newOrderWeight = 100;
    public ?int $forceStatusTruckId = null;
    public ?string $forceStatusNew = null;
    public ?int $editMinerStatusId = null;
    public ?string $editMinerStatusNew = null;
    public string $pausePeriod = 'shift';
    public array $pauseTypes = [];
    public array $minerPauseTypes = []; // Фильтр по статусам забоев

    // Порода для загруженного грузовика
    public ?int $loadedTruckRockId = null;
    public array $alertFilters = [
        'type'     => [],  // truck_anomaly, productivity_drop, empty_run_high, zone_overflow, zone_volume_action, avg_distance_high, maintenance_predict
        'severity' => [],  // info, warning, critical
        'entity'   => [],  // truck, miner, zone, fleet
        'status'   => [],  // new, acknowledged (по умолчанию active = оба)
    ];
    public int $minerThreshold = 3;
    public int $zoneThreshold = 3;
    public int $fuelingPostsCount = 2;
    public int $maintenancePostsCount = 2;
    public int $tireServicePostsCount = 3;
    public int $to1IntervalHours = 250;
    public int $to2IntervalHours = 500;
    public float $emptyRunCoefficient = 0.5;
    public float $avgDistanceThreshold = 5.0;

    public function boot(
        RouteAssignmentService $routeService,
        RouteOptimizerService $optimizerService,
        ZoneStatusService $zoneStatusService,
        ShiftPlanningService $planningService
    ) {
        $this->routeService = $routeService;
        $this->optimizerService = $optimizerService;
        $this->zoneStatusService = $zoneStatusService;
        $this->planningService = $planningService;
    }

    public function mount()
    {
        $this->loadData();
        $this->loadThresholds();
        $this->loadServicePostSettings();
        // zoneSharingThreshold убран — больше не используется (переход на зонный перебор)
        $this->avgDistanceThreshold = SystemSetting::getAvgDistanceThreshold();
    }

    /**
     * Обработчик изменения порога адаптивной балансировки — сохраняет в БД.
     */
    public function updatedZoneSharingThreshold(int $value): void
    {
        SystemSetting::setZoneSharingThreshold($value);
        $this->dispatch('notify', [
            'type' => 'info',
            'message' => "Порог адаптивной балансировки: {$value}%. Применится при следующей оптимизации.",
        ]);
    }

    public function loadData(): void
    {
        $this->trucks = Truck::with(['truckModel', 'driver', 'trips' => function ($q) {
            $q->whereNull('completed_at')
                ->with([
                    'rock',
                    'zone',
                    'zone.rocks',
                    'miner.rocks',
                    'miner.currentRock',
                    'dump',
                    'miningOrder.rock',
                    'pauses' => function ($q) {
                        $q->whereNull('ended_at');
                    },
                ])
                ->latest();
        }])->get();

        // ID зон, которые сейчас под обваловкой — для отметки в таблице самосвалов
        $this->bermZoneIds = \App\Models\BermRequest::active()->pluck('zone_id')->toArray();

        $this->miners = Miner::with(['rocks', 'currentRock'])->get();

        $this->dumps = Dump::with(['zones.rocks'])->get();

        $this->zones = Zone::with(['dump', 'rocks'])->get();

        $this->rocks = Rock::all();

        $this->orders = MiningOrder::with(['miner.rocks', 'dump', 'zone.rocks', 'rock'])
            ->where('active', true)
            ->get();
    }

    public function updatedSelectedMinerId(?int $value): void
    {
        $this->selectedOrderId = null;
        $this->selectedZoneId = null;
        $this->availableOrders = [];
        $this->availableZones = [];

        if ($value) {
            $miner = Miner::with('rocks')->find($value);
            $minerRock = $miner?->rocks?->first();

            if (!$minerRock) return;

            // Получаем маршруты с доступными зонами
            $orders = MiningOrder::with(['dump.zones.rocks'])
                ->where('miner_id', $value)
                ->where('active', true)
                ->get();

            $this->availableOrders = $orders->filter(function ($order) use ($minerRock) {
                // Проверяем есть ли доступные зоны для породы
                return $order->dump && $order->dump->zones->contains(function ($zone) use ($minerRock) {
                    return $zone->delivery
                        && $zone->volume < $zone->capacity
                        && $zone->rocks->contains('id', $minerRock->id);
                });
            })->map(fn($o) => [
                'id' => $o->id,
                'dump_name' => $o->dump?->name_dump,
                'distance' => $o->distance_km,
            ])->values()->toArray();
        }
    }

    public function updatedSelectedOrderId(?int $value): void
    {
        $this->selectedZoneId = null;
        $this->availableZones = [];

        if ($value && $this->selectedMinerId) {
            $miner = Miner::with('rocks')->find($this->selectedMinerId);
            $minerRock = $miner?->rocks?->first();
            $order = MiningOrder::with('dump.zones.rocks')->find($value);

            if ($minerRock && $order?->dump) {
                $this->availableZones = $order->dump->zones
                    ->filter(function ($zone) use ($minerRock) {
                        return $zone->delivery
                            && $zone->volume < $zone->capacity
                            && $zone->rocks->contains('id', $minerRock->id);
                    })
                    ->map(fn($z) => [
                        'id' => $z->id,
                        'name' => $z->name_zone,
                        'volume' => $z->volume,
                        'capacity' => $z->capacity,
                        'fill' => round($z->volume / $z->capacity * 100),
                    ])
                    ->sortBy('fill')
                    ->values()
                    ->toArray();
            }
        }
    }

    public function assignRoute(): void
    {
        $this->validate([
            'selectedTruckId' => 'required|exists:trucks,id',
        ]);

        $truck = Truck::find($this->selectedTruckId);

        if (!$truck) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Грузовик не найден']);
            return;
        }

        // Запрещаем назначать маршрут только сломанным грузовикам
        if ($truck->status === 'breakdown') {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Самосвал в поломке']);
            return;
        }

        // Проверяем, загружен ли грузовик (нужна только зона)
        $isLoaded = in_array($truck->status, $this->getLoadedTruckStatuses());

        if ($isLoaded) {
            // === ГРУЗОВИК УЖЕ ЗАГРУЖЕН - только назначаем зону разгрузки ===
            $this->assignZoneToLoadedTruck($truck);
            return;
        }

        // === ОБЫЧНОЕ НАЗНАЧЕНИЕ МАРШРУТА ===
        $this->validate([
            'selectedOrderId' => 'required|exists:mining_orders,id',
        ]);

        $order = MiningOrder::find($this->selectedOrderId);
        $zone = $this->selectedZoneId ? Zone::find($this->selectedZoneId) : null;

        if (!$order) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Маршрут не найден']);
            return;
        }

        // Если грузовик уже имеет активный рейс - завершаем его
        $activeTrip = TruckTrip::where('truck_id', $truck->id)
            ->whereNull('completed_at')
            ->latest()
            ->first();

        if ($activeTrip) {
            $activeTrip->update(['completed_at' => now()]);
        }

        if (!$zone) {
            // Выбираем зону автоматически по породе из заказа
            if ($order->rock_id) {
                $zone = $this->routeService->selectZoneForRock($order->dump_id, $order->rock_id);
            }
        }

        if (!$zone) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Нет доступной зоны для данного маршрута']);
            return;
        }

        try {
            TruckTrip::create([
                'truck_id' => $truck->id,
                'driver_id' => $truck->driver_id,
                'miner_id' => $order->miner_id,
                'dump_id' => $order->dump_id,
                'zone_id' => $zone->id,
                'mining_order_id' => $order->id,
                'started_at' => now()
            ]);

            $truck->update([
                'status' => Truck::STATUS_TO_MINER,
                'route_version' => $truck->route_version + 1,
                'current_load' => 0, // Сбрасываем загрузку
            ]);

            $order->update([
                'truck_id' => $truck->id,
                'zone_id' => $zone->id,
                'last_assigned_at' => now()
            ]);

            event(new DriverRouteUpdated($truck, $order));

            // Уведомляем экскаваторщика о новом грузовике
            if ($order->miner_id) {
                event(new ExcavatorNotification(
                    $order->miner_id,
                    'truck_assigned',
                    [
                        'truck_id' => $truck->id,
                        'truck_number' => $truck->number,
                        'driver_name' => $truck->driver?->name,
                        'status' => 'to_miner',
                        'message' => "Самосвал {$truck->number} направляется к забою",
                    ]
                ));
            }

            $this->reset(['selectedTruckId', 'selectedMinerId', 'selectedOrderId', 'selectedZoneId']);
            $this->availableOrders = [];
            $this->availableZones = [];
            $this->loadData();

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => "Маршрут назначен: {$truck->number} → {$order->miner?->name_miner} → {$zone->name_zone}",
            ]);

        } catch (\Exception $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function assignAllFree(): void
    {
        try {
            $count = $this->routeService->assignRoutesToAllFree();
            $this->loadData();
            $this->dispatch('notify', ['type' => 'success', 'message' => "Назначено маршрутов: {$count}"]);
        } catch (\Exception $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    /**
     * При изменении породы для загруженного грузовика
     */
    public function updatedLoadedTruckRockId(?int $value): void
    {
        if (!$value || !$this->selectedTruckId) {
            return;
        }

        $truck = Truck::with(['trips' => function ($q) {
            $q->whereNull('completed_at')->latest();
        }])->find($this->selectedTruckId);

        if (!$truck || !in_array($truck->status, $this->getLoadedTruckStatuses())) {
            return;
        }

        $trip = $truck->trips->first();
        if (!$trip) {
            return;
        }

        // Обновляем породу в рейсе
        $trip->update(['rock_id' => $value]);

        // Обновляем доступные зоны для новой породы - ищем на ВСЕХ отвалах
        $rock = \App\Models\Rock::find($value);

        if ($rock) {
            // Ищем зоны на всех отвалах
            $allZones = \App\Models\Zone::with(['dump', 'rocks'])
                ->where('delivery', true)
                ->whereRaw('volume < capacity')
                ->whereHas('rocks', function ($q) use ($rock) { $acceptable = \App\Services\RouteAssignmentService::ROCK_FALLBACK_CHAIN[$rock->name_rock] ?? [$rock->name_rock]; $q->whereIn('rocks.name_rock', $acceptable); })
                ->get();

            $this->availableZones = $allZones->map(fn($z) => [
                'id' => $z->id,
                'name' => $z->name_zone,
                'dump_name' => $z->dump?->name_dump,
                'volume' => $z->volume,
                'capacity' => $z->capacity,
                'fill' => round($z->volume / $z->capacity * 100),
            ])
            ->sortBy('fill')
            ->values()
            ->toArray();
        }

        // Сбрасываем выбранную зону
        $this->selectedZoneId = null;

        $this->dispatch('notify', [
            'type' => 'info',
            'message' => "Порода изменена на: {$rock?->name_rock}",
        ]);
    }

    /**
     * При выборе грузовика - автозаполнение для загруженных
     */
    public function updatedSelectedTruckId(?int $value): void
    {
        $this->selectedMinerId = null;
        $this->selectedOrderId = null;
        $this->selectedZoneId = null;
        $this->availableOrders = [];
        $this->availableZones = [];
        $this->loadedTruckRockId = null;

        if (!$value) {
            return;
        }

        $truck = $this->trucks->firstWhere('id', $value);

        // Если грузовик уже загружен - автозаполняем данные из текущего рейса
        if ($truck && in_array($truck->status, $this->getLoadedTruckStatuses())) {
            $trip = $truck->trips->first();

            if ($trip) {
                // Автозаполняем забой и маршрут из текущего рейса
                $this->selectedMinerId = $trip->miner_id;

                // Определяем породу: сначала из trip->rock, потом из miner->currentRock
                $rock = $trip->rock;
                if (!$rock && $trip->miner) {
                    $rock = $trip->miner->currentRock;
                }

                // Устанавливаем породу
                $this->loadedTruckRockId = $rock?->id;

                // Ищем зоны на ВСЕХ отвалах для данной породы
                if ($rock) {
                    $allZones = \App\Models\Zone::with(['dump', 'rocks'])
                        ->where('delivery', true)
                        ->whereRaw('volume < capacity')
                        ->whereHas('rocks', function ($q) use ($rock) { $acceptable = \App\Services\RouteAssignmentService::ROCK_FALLBACK_CHAIN[$rock->name_rock] ?? [$rock->name_rock]; $q->whereIn('rocks.name_rock', $acceptable); })
                        ->get();

                    $this->availableZones = $allZones->map(fn($z) => [
                        'id' => $z->id,
                        'name' => $z->name_zone,
                        'dump_name' => $z->dump?->name_dump,
                        'volume' => $z->volume,
                        'capacity' => $z->capacity,
                        'fill' => round($z->volume / $z->capacity * 100),
                    ])
                    ->sortBy('fill')
                    ->values()
                    ->toArray();
                }

                // Для загруженного грузовика маршрут уже определён
                if ($trip->mining_order_id) {
                    $this->selectedOrderId = $trip->mining_order_id;
                }
            }
        }
    }

    #[On('truck-status-changed')]
    public function onTruckStatusChanged(array $data): void
    {
        $this->loadData();
    }

    #[On('refresh-dispatcher-data')]
    public function onRefreshData(): void
    {
        $this->loadData();
    }

    // =========================================
    // УПРАВЛЕНИЕ ВКЛАДКАМИ
    // =========================================

    public function setActiveTab(string $tab): void
    {
        $this->activeTab = $tab;

        // Обновляем данные при переключении на вкладку самосвалов
        if ($tab === 'trucksTab') {
            $this->loadData();
        }
    }

    /**
     * EVENT-DRIVEN: забой ОСТАНОВЛЕН.
     *
     * Что происходит:
     *   1. Деактивируем все MiningOrder этого забоя
     *   2. Самосвалы на маршрутах к этому забою → освобождаются
     *   3. Переназначаем свободные самосвалы (assignRoutesToAllFree)
     *   4. Если auto-режим → полная оптимизация (optimize)
     */
    protected function onMinerStopped(int $minerId): void
    {
        $miner = Miner::find($minerId);
        if (!$miner) return;

        Log::info('EVENT: забой остановлен — пересмотр маршрутов', [
            'miner_id'   => $minerId,
            'miner_name' => $miner->name_miner,
        ]);

        try {
            // 1. Деактивируем маршруты этого забоя
            $deactivated = MiningOrder::where('miner_id', $minerId)
                ->where('active', true)
                ->update(['active' => false]);

            Log::info('EVENT: деактивировано маршрутов', [
                'miner_id'    => $minerId,
                'deactivated' => $deactivated,
            ]);

            // 2. Освобождаем самосвалы, которые ехали к этому забою
            $trips = TruckTrip::whereNull('completed_at')
                ->where('miner_id', $minerId)
                ->get();

            foreach ($trips as $trip) {
                $trip->update([
                    'completed_at'  => now(),
                    'load_volume'   => 0,
                ]);
                $trip->truck->update(['status' => Truck::STATUS_COMPLETED]);
            }

            // 3. Переназначаем свободные самосвалы
            app(RouteAssignmentService::class)->assignRoutesToAllFree();

            // 4. Если auto-режим → оптимизация
            if (SystemSetting::isManualMode() === false) {
                $optimizer = app(RouteOptimizerService::class);
                $optimizer->optimize();
            }

            // Уведомление диспетчеру
            $this->dispatch('notify', [
                'type' => 'info',
                'message' => "Маршруты забоя {$miner->name_miner} пересмотрены автоматически",
            ]);
        } catch (\Exception $e) {
            Log::error('EVENT: onMinerStopped failed', [
                'miner_id' => $minerId,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    /**
     * EVENT-DRIVEN: забой ЗАПУЩЕН (вышел из ремонта/остановки).
     *
     * Что происходит:
     *   1. Создаём MiningOrder пары (забой → все отвалы, если их нет)
     *   2. Синхронизируем расстояния
     *   3. Если auto-режим → полная оптимизация (optimize)
     *   4. Переназначаем свободные самосвалы
     */
    protected function onMinerStarted(int $minerId): void
    {
        $miner = Miner::find($minerId);
        if (!$miner) return;

        Log::info('EVENT: забой запущен — создание пар + оптимизация', [
            'miner_id'   => $minerId,
            'miner_name' => $miner->name_miner,
        ]);

        try {
            // 1. Создаём пары (забой → все отвалы), если их ещё нет
            $dumps = Dump::all();
            $created = 0;
            foreach ($dumps as $dump) {
                $existing = MiningOrder::where('miner_id', $minerId)
                    ->where('dump_id', $dump->id)
                    ->exists();

                if (!$existing) {
                    // Берём расстояние из miner_dump_distances
                    $distance = \App\Models\MinerDumpDistance::where('miner_id', $minerId)
                        ->where('dump_id', $dump->id)
                        ->value('distance_km');

                    MiningOrder::create([
                        'miner_id'    => $minerId,
                        'dump_id'     => $dump->id,
                        'distance_km' => $distance,
                        'active'      => false, // станет active после optimize()
                        'weight'      => 100,
                    ]);
                    $created++;
                }
            }

            Log::info('EVENT: создано пар забой-отвал', [
                'miner_id'  => $minerId,
                'created'   => $created,
            ]);

            // 2. Если auto-режим → оптимизация
            if (SystemSetting::isManualMode() === false) {
                $optimizer = app(RouteOptimizerService::class);
                $optimizer->optimize();
            }

            // 3. Переназначаем свободные самосвалы
            app(RouteAssignmentService::class)->assignRoutesToAllFree();

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => "Забой {$miner->name_miner} запущен. Маршруты пересмотрены" .
                    ($created > 0 ? " (создано пар: {$created})" : '') . ".",
            ]);
        } catch (\Exception $e) {
            Log::error('EVENT: onMinerStarted failed', [
                'miner_id' => $minerId,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    public function render()
    {
        return view('livewire.main-dispatcher-panel');
    }
}