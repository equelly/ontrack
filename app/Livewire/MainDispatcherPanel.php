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
use App\Events\DriverRouteUpdated;
use App\Services\RouteAssignmentService;
use App\Services\RouteOptimizerService;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Livewire\Attributes\On;

class MainDispatcherPanel extends Component
{
    public $trucks;
    public $miners;
    public $dumps;
    public $zones;
    public $rocks;
    public $orders;

    // Вкладки
    public string $activeTab = 'trucksTab';

    // Назначение маршрута
    public ?int $selectedTruckId = null;
    public ?int $selectedMinerId = null;
    public ?int $selectedOrderId = null;
    public ?int $selectedZoneId = null;

    public $availableOrders = [];
    public $availableZones = [];

    // Управление маршрутами
    public ?int $editOrderId = null;
    public ?int $editDumpId = null;
    public ?int $editRockId = null;
    public ?bool $editActive = null;
    public ?int $editWeight = null; // Вес маршрута
    public array $editDistances = []; // Расстояния от забоя до перегрузок

    // Принудительная смена статуса
    public ?int $forceStatusTruckId = null;
    public ?string $forceStatusNew = null;

    // Выбор зоны для ожидающих грузовиков
    public array $waitingZoneSelection = [];

    // Фильтры простоев
    public string $pausePeriod = 'shift';
    public array $pauseTypes = [];

    protected RouteAssignmentService $routeService;
    protected RouteOptimizerService $optimizerService;

    public function boot(RouteAssignmentService $routeService, RouteOptimizerService $optimizerService)
    {
        $this->routeService = $routeService;
        $this->optimizerService = $optimizerService;
    }

    public function mount()
    {
        $this->loadData();
    }

    public function loadData(): void
    {
        Log::info('=== MainDispatcherPanel loadData START ===');
        
        $this->trucks = Truck::with(['truckModel', 'driver', 'trips' => function ($q) {
            $q->whereNull('completed_at')
                ->with([
                    'rock',
                    'zone.rocks',
                    'miner.currentRock',
                    'miner.rocks',
                    'dump',
                    'pauses' => function ($q) {
                        $q->whereNull('ended_at'); // Только активные паузы
                    },
                ])
                ->latest(); // ВАЖНО: берём последний trip!
        }])->get();

        // Полное логирование всех данных рейса
        foreach ($this->trucks as $truck) {
            $trip = $truck->trips->first(); // После latest() первый = последний
            if ($trip) {
                Log::info('TRUCK DATA', [
                    'truck_id' => $truck->id,
                    'truck_number' => $truck->number,
                    'truck_status' => $truck->status,
                    '--- TRIP ---' => '---',
                    'trip_id' => $trip->id,
                    'trip_miner_id' => $trip->miner_id,
                    'trip_miner_name' => $trip->miner?->name_miner,
                    'trip_dump_id' => $trip->dump_id,
                    'trip_dump_name' => $trip->dump?->name_dump,
                    'trip_zone_id' => $trip->zone_id,
                    'trip_zone_name' => $trip->zone?->name_zone,
                    'trip_rock_id' => $trip->rock_id,
                    'trip_rock_name' => $trip->rock?->name_rock,
                    '--- MINER ROCK ---' => '---',
                    'miner_current_rock' => $trip->miner?->currentRock?->name_rock,
                    'miner_first_rock' => $trip->miner?->rocks?->first()?->name_rock,
                    '--- ZONE ROCK ---' => '---',
                    'zone_rock_name' => $trip->zone?->rocks?->first()?->name_rock,
                ]);
            }
        }

        $this->miners = Miner::with(['rocks', 'currentRock'])->get();

        $this->dumps = Dump::with(['zones.rocks'])->get();

        $this->zones = Zone::with(['dump', 'rocks'])->get();

        $this->rocks = Rock::all();

        $this->orders = MiningOrder::with(['miner.rocks', 'miner.currentRock', 'dump', 'zone.rocks', 'rock'])
            ->where('active', true)
            ->get();
        
        Log::info('=== MainDispatcherPanel loadData END ===');
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
            'selectedOrderId' => 'required|exists:mining_orders,id',
        ]);

        $truck = Truck::find($this->selectedTruckId);
        $order = MiningOrder::find($this->selectedOrderId);
        $zone = $this->selectedZoneId ? Zone::find($this->selectedZoneId) : null;

        if (!$truck || !$order) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Данные не найдены']);
            return;
        }

        if (!in_array($truck->status, ['free', 'completed'])) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Самосвал занят']);
            return;
        }

        if (!$zone) {
            // Выбираем зону автоматически по породе из заказа или текущей породе забоя
            $rockId = $order->rock_id;
            if (!$rockId) {
                $rockId = $order->miner?->current_rock_id ?? $order->miner?->rocks->first()?->id;
            }
            if ($rockId) {
                $zone = $this->routeService->selectZoneForRock($order->dump_id, $rockId);
            }
        }

        if (!$zone) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Нет доступной зоны для данного маршрута']);
            return;
        }

        // Определяем породу для trip
        $rockId = $order->rock_id;
        if (!$rockId) {
            $rockId = $order->miner?->current_rock_id ?? $order->miner?->rocks->first()?->id;
        }

        try {
            TruckTrip::create([
                'truck_id' => $truck->id,
                'driver_id' => $truck->driver_id,
                'miner_id' => $order->miner_id,
                'dump_id' => $order->dump_id,
                'zone_id' => $zone->id,
                'rock_id' => $rockId,
                'mining_order_id' => $order->id,
                'started_at' => now()
            ]);

            $truck->update([
                'status' => Truck::STATUS_TO_MINER,
                'route_version' => $truck->route_version + 1
            ]);

            $order->update([
                'truck_id' => $truck->id,
                'zone_id' => $zone->id,
                'last_assigned_at' => now()
            ]);

            event(new DriverRouteUpdated($truck, $order));

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

    // =========================================
    // УПРАВЛЕНИЕ МАРШРУТАМИ (mining_orders)
    // =========================================

    public function openEditOrder(int $orderId): void
    {
        $order = MiningOrder::find($orderId);
        if ($order) {
            $this->editOrderId = $orderId;
            $this->editDumpId = $order->dump_id;
            $this->editRockId = $order->rock_id;
            $this->editActive = $order->active;

            // Загружаем расстояния от забоя до всех перегрузок из других маршрутов
            $this->editDistances = MiningOrder::where('miner_id', $order->miner_id)
                ->whereNotNull('distance_km')
                ->get()
                ->groupBy('dump_id')
                ->map(fn($group) => $group->first()->distance_km)
                ->toArray();
        }
    }

    public function closeEditOrder(): void
    {
        $this->editOrderId = null;
        $this->editDumpId = null;
        $this->editRockId = null;
        $this->editActive = null;
        $this->editDistances = [];
    }

    public function saveOrder(): void
    {
        if (!$this->editOrderId) {
            return;
        }

        $order = MiningOrder::find($this->editOrderId);
        if (!$order) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Маршрут не найден']);
            return;
        }

        // Получаем расстояние для выбранной перегрузки
        $distance = $this->editDistances[$this->editDumpId] ?? $order->distance_km;

        $order->update([
            'dump_id' => $this->editDumpId,
            'rock_id' => $this->editRockId,
            'active' => $this->editActive,
            'distance_km' => $distance,
        ]);

        $this->loadData();
        $this->closeEditOrder();
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Маршрут обновлён']);
    }

    public function toggleOrderActive(int $orderId): void
    {
        $order = MiningOrder::find($orderId);
        if ($order) {
            $order->update(['active' => !$order->active]);
            $this->loadData();
            $status = $order->active ? 'активирован' : 'деактивирован';
            $this->dispatch('notify', ['type' => 'info', 'message' => "Маршрут {$status}"]);
        }
    }

    public function deleteOrder(int $orderId): void
    {
        $order = MiningOrder::find($orderId);
        if (!$order) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Маршрут не найден']);
            return;
        }

        // Проверяем есть ли активные назначения
        $hasActiveTrips = TruckTrip::where('mining_order_id', $orderId)
            ->whereNull('completed_at')
            ->exists();

        if ($hasActiveTrips) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Нельзя удалить маршрут с активными назначениями']);
            return;
        }

        $order->delete();
        $this->loadData();
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Маршрут удалён']);
    }

    public function createNewOrder(): void
    {
        // Создаём новый маршрут (потребуется выбрать miner, dump, rock)
        // Для простоты - открываем модальное окно с формой
        // Можно расширить позже
    }

    public function getOrdersForManagementProperty()
    {
        return MiningOrder::with(['miner.currentRock', 'dump.zones.rocks'])
            ->orderBy('miner_id')
            ->orderBy('active', 'desc') // Активные первыми
            ->orderBy('weight', 'desc')
            ->get()
            ->map(function ($order) {
                // Текущая порода в забое
                $currentRock = $order->miner?->currentRock;
                
                // Доступные зоны для текущей породы на перегрузке
                $availableZones = collect();
                if ($currentRock && $order->dump) {
                    $availableZones = $order->dump->zones->filter(function ($zone) use ($currentRock) {
                        return $zone->delivery 
                            && $zone->volume < $zone->capacity
                            && $zone->rocks->contains('id', $currentRock->id);
                    })->map(fn($z) => [
                        'id' => $z->id,
                        'name' => $z->name_zone,
                        'fill' => $z->capacity > 0 ? round($z->volume / $z->capacity * 100) : 0,
                    ]);
                }
                
                // Расстояние из miner_dump_distances
                $distance = \App\Models\MinerDumpDistance::where('miner_id', $order->miner_id)
                    ->where('dump_id', $order->dump_id)
                    ->value('distance_km');
                
                return (object)[
                    'id' => $order->id,
                    'miner' => $order->miner,
                    'dump' => $order->dump,
                    'current_rock' => $currentRock,
                    'distance_km' => $distance,
                    'weight' => $order->weight ?? 100,
                    'active' => $order->active,
                    'available_zones' => $availableZones,
                    'has_zones' => $availableZones->isNotEmpty(),
                ];
            });
    }

    /**
     * Группировка маршрутов по забоям
     */
    public function getOrdersGroupedByMinerProperty()
    {
        return $this->getOrdersForManagementProperty()
            ->groupBy(fn($o) => $o->miner?->name_miner ?? 'Без забоя');
    }

    /**
     * Оптимизировать маршруты (выбрать лучшие для каждого забоя)
     */
    public function optimizeRoutes(): void
    {
        try {
            $result = $this->optimizerService->optimize();
            
            $this->loadData();
            
            $message = sprintf(
                'Оптимизация завершена: %d активных маршрутов в %d раундах',
                $result['stats']['active_routes'],
                $result['stats']['rounds_count']
            );
            
            $this->dispatch('notify', ['type' => 'success', 'message' => $message]);
            
            Log::info('Routes optimized from UI', $result['stats']);
        } catch (\Exception $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Ошибка оптимизации: ' . $e->getMessage()]);
            Log::error('optimizeRoutes error', ['message' => $e->getMessage()]);
        }
    }

    /**
     * Активировать резервный маршрут вручную
     */
    public function activateOrder(int $orderId): void
    {
        $order = MiningOrder::find($orderId);
        
        if (!$order) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Маршрут не найден']);
            return;
        }
        
        $order->update(['active' => true]);
        $this->loadData();
        
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Маршрут активирован']);
    }

    /**
     * Деактивировать маршрут
     */
    public function deactivateOrder(int $orderId): void
    {
        $order = MiningOrder::find($orderId);
        
        if (!$order) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Маршрут не найден']);
            return;
        }
        
        $order->update(['active' => false]);
        $this->loadData();
        
        $this->dispatch('notify', ['type' => 'info', 'message' => 'Маршрут деактивирован']);
    }

    // =========================================
    // УПРАВЛЕНИЕ РЕЖИМОМ АКТИВАЦИИ
    // =========================================

    /**
     * Получить текущий режим активации
     */
    public function getRouteModeProperty(): string
    {
        return SystemSetting::getRouteActivationMode();
    }

    /**
     * Переключить режим активации
     */
    public function toggleRouteMode(): void
    {
        $currentMode = SystemSetting::getRouteActivationMode();
        $newMode = $currentMode === 'auto' ? 'manual' : 'auto';
        
        SystemSetting::setRouteActivationMode($newMode);
        $this->loadData();
        
        $modeLabel = $newMode === 'auto' ? 'автоматический' : 'ручной';
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => "Режим изменён на {$modeLabel}"
        ]);
    }

    /**
     * Установить режим активации
     */
    public function setRouteMode(string $mode): void
    {
        if (!in_array($mode, ['auto', 'manual'])) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Неверный режим']);
            return;
        }
        
        SystemSetting::setRouteActivationMode($mode);
        $this->loadData();
        
        $modeLabel = $mode === 'auto' ? 'автоматический' : 'ручной';
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => "Установлен {$modeLabel} режим"
        ]);
    }

    // =========================================
    // УПРАВЛЕНИЕ ВЕСОМ МАРШРУТОВ
    // =========================================

    /**
     * Открыть форму редактирования веса
     */
    public function openWeightEditor(int $orderId): void
    {
        $order = MiningOrder::find($orderId);
        if ($order) {
            $this->editOrderId = $orderId;
            $this->editWeight = $order->weight ?? 100;
        }
    }

    /**
     * Сохранить вес маршрута
     */
    public function saveWeight(): void
    {
        if (!$this->editOrderId) {
            return;
        }

        $order = MiningOrder::find($this->editOrderId);
        if (!$order) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Маршрут не найден']);
            return;
        }

        $weight = max(1, min(1000, (int)$this->editWeight));
        $order->update(['weight' => $weight]);
        
        $this->editOrderId = null;
        $this->editWeight = null;
        $this->loadData();
        
        $this->dispatch('notify', ['type' => 'success', 'message' => "Вес маршрута изменён на {$weight}"]);
    }

    /**
     * Закрыть форму редактирования веса
     */
    public function closeWeightEditor(): void
    {
        $this->editOrderId = null;
        $this->editWeight = null;
    }

    /**
     * Быстрое изменение веса (+10 или -10)
     */
    public function adjustWeight(int $orderId, int $delta): void
    {
        $order = MiningOrder::find($orderId);
        if (!$order) {
            return;
        }

        $newWeight = max(1, min(1000, ($order->weight ?? 100) + $delta));
        $order->update(['weight' => $newWeight]);
        $this->loadData();
    }

    public function toggleZone(int $zoneId, bool $delivery): void
    {
        $zone = Zone::find($zoneId);

        if (!$zone) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Зона не найдена']);
            return;
        }

        $zone->update(['delivery' => $delivery]);

        if (!$delivery) {
            $this->routeService->reassignOnZoneClose($zone);
        }

        $this->loadData();

        $this->dispatch('notify', [
            'type' => 'info',
            'message' => $delivery ? "Зона {$zone->name_zone} открыта" : "Зона {$zone->name_zone} закрыта",
        ]);
    }

    public function getFreeTrucksCountProperty(): int
    {
        // Свободные = в отстое + ожидающие назначения
        return $this->trucks->whereIn('status', ['free', 'completed'])->count();
    }

    public function getWorkingTrucksCountProperty(): int
    {
        // В работе = все кроме free, completed, breakdown
        return $this->trucks->whereNotIn('status', ['free', 'completed', 'breakdown'])->count();
    }

    public function getActiveMinersCountProperty(): int
    {
        return $this->miners->where('active', true)->count();
    }

    public function getBreakdownCountProperty(): int
    {
        return $this->trucks->where('status', 'breakdown')->count();
    }

    public function getFreeTrucksProperty()
    {
        // Грузовики, которым можно назначить маршрут: free или completed
        return $this->trucks->whereIn('status', ['free', 'completed']);
    }

    /**
     * Активные экскаваторы с породой в забое
     */
    public function getActiveMinersWithRockProperty()
    {
        return $this->miners
            ->filter(fn($m) => $m->active && $m->rocks->isNotEmpty())
            ->values();
    }

    #[On('truck-status-changed')]
    public function onTruckStatusChanged(array $data = []): void
    {
        $this->loadData();
    }

    #[On('echo:dispatcher,.truck-updated')]
    public function onTruckUpdated(): void
    {
        Log::info('MainDispatcherPanel: truck-updated received via Echo');
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

    // =========================================
    // ПРИНУДИТЕЛЬНАЯ СМЕНА СТАТУСА
    // =========================================

    public function openForceStatusModal(int $truckId): void
    {
        $this->forceStatusTruckId = $truckId;
        $this->forceStatusNew = null;
    }

    public function closeForceStatusModal(): void
    {
        $this->forceStatusTruckId = null;
        $this->forceStatusNew = null;
    }

    public function forceChangeStatus(): void
    {
        if (!$this->forceStatusTruckId || !$this->forceStatusNew) {
            return;
        }

        $truck = Truck::find($this->forceStatusTruckId);
        if (!$truck) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Самосвал не найден']);
            return;
        }

        $oldStatus = $truck->status;

        // Если переводим в свободный - завершаем текущий рейс
        if ($this->forceStatusNew === 'free') {
            $activeTrip = $truck->trips->first();
            if ($activeTrip) {
                $activeTrip->update(['completed_at' => now()]);
            }
        }

        $truck->update(['status' => $this->forceStatusNew]);

        Log::warning('DISPATCHER FORCE STATUS CHANGE', [
            'truck_id' => $truck->id,
            'truck_number' => $truck->number,
            'old_status' => $oldStatus,
            'new_status' => $this->forceStatusNew,
        ]);

        $this->closeForceStatusModal();
        $this->loadData();

        $this->dispatch('notify', [
            'type' => 'warning',
            'message' => "Статус {$truck->number} изменён: {$oldStatus} → {$this->forceStatusNew}",
        ]);
    }

    public function getAvailableStatusesProperty(): array
    {
        return [
            'free' => 'В отстое',
            'completed' => 'Ожидает назначения',
            'to_miner' => 'К забою',
            'loading' => 'Погрузка',
            'transporting' => 'Перевозка',
            'unloading' => 'Разгрузка',
            'breakdown' => 'Поломка',
            'delayed' => 'Задержка',
        ];
    }

    // =========================================
    // УПРАВЛЕНИЕ ЗАБОЯМИ
    // =========================================

    public function toggleMinerStatus(int $minerId): void
    {
        $miner = Miner::find($minerId);
        if ($miner) {
            $miner->update(['active' => !$miner->active]);
            $this->loadData();
            $status = $miner->active ? 'активирован' : 'деактивирован';
            $this->dispatch('notify', ['type' => 'info', 'message' => "Забой {$miner->name_miner} {$status}"]);
        }
    }

    // =========================================
    // СТАТИСТИКА
    // =========================================

    public function getPlannedDistanceStatsProperty(): array
    {
        // Получаем активные маршруты с расстоянием
        $activeOrders = MiningOrder::where('active', true)
            ->whereNotNull('distance_km')
            ->get();

        $avgDistance = $activeOrders->avg('distance_km');

        return [
            'avg_distance' => $avgDistance ? round($avgDistance, 1) : '—',
            'total_orders' => $activeOrders->count(),
        ];
    }

    // =========================================
    // ПРОСТОИ И ЗАДЕРЖКИ
    // =========================================

    public function getPauseStatsProperty(): array
    {
        $query = TripPause::with(['truck', 'trip'])
            ->orderBy('started_at', 'desc');

        // Период
        switch ($this->pausePeriod) {
            case 'today':
                $query->whereDate('started_at', today());
                $periodLabel = 'За сегодня';
                break;
            case 'week':
                $query->where('started_at', '>=', now()->subWeek());
                $periodLabel = 'За неделю';
                break;
            case 'month':
                $query->where('started_at', '>=', now()->subMonth());
                $periodLabel = 'За месяц';
                break;
            default: // shift
                $query->where('started_at', '>=', now()->startOfDay());
                $periodLabel = 'За смену';
        }

        // Фильтр по типам
        if (!empty($this->pauseTypes)) {
            $query->whereIn('type', $this->pauseTypes);
        }

        $pauses = $query->get();

        // Группировка по типам
        $byType = $pauses->groupBy('type')->map(function ($group, $type) {
            $totalSeconds = $group->sum(function ($p) {
                return $p->getCurrentDuration();
            });
            return [
                'type' => $type,
                'label' => TripPause::typeLabel($type),
                'count' => $group->count(),
                'total_seconds' => $totalSeconds,
                'total_formatted' => $this->formatSeconds($totalSeconds),
            ];
        })->values();

        $totalSeconds = $pauses->sum(function ($p) {
            return $p->getCurrentDuration();
        });

        return [
            'pauses' => $pauses,
            'total_count' => $pauses->count(),
            'total_seconds' => $totalSeconds,
            'total_formatted' => $this->formatSeconds($totalSeconds),
            'active_count' => $pauses->whereNull('ended_at')->count(),
            'by_type' => $byType,
            'period_label' => $periodLabel,
        ];
    }

    private function formatSeconds(int $seconds): string
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        
        if ($hours > 0) {
            return "{$hours}ч {$minutes}м";
        }
        return "{$minutes}м";
    }

    /**
     * Получить грузовики, ожидающие решения по зоне
     */
    public function getTrucksWaitingForZoneProperty()
    {
        return $this->trucks->filter(function ($truck) {
            $trip = $truck->trips->first();
            // Грузовик в статусе waiting_unloading без зоны - ждёт решения диспетчера
            return $truck->status === 'waiting_unloading'
                && $trip
                && $trip->load_volume
                && !$trip->zone_id;
        })->map(function ($truck) {
            $trip = $truck->trips->first();
            return (object)[
                'truck' => $truck,
                'trip' => $trip,
                'rock' => $trip->rock,
                'volume' => $trip->load_volume,
                'miner' => $trip->miner,
            ];
        })->values();
    }

    /**
     * Назначить зону для грузовика, ожидающего решения
     */
    public function assignZoneToWaitingTruck(int $truckId): void
    {
        $zoneId = $this->waitingZoneSelection[$truckId] ?? null;
        
        if (!$zoneId) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Выберите зону']);
            return;
        }

        $truck = Truck::find($truckId);
        $zone = Zone::with('dump')->find($zoneId);

        if (!$truck || !$zone) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Данные не найдены']);
            return;
        }

        $trip = TruckTrip::where('truck_id', $truckId)
            ->whereNull('completed_at')
            ->latest()
            ->first();

        if (!$trip) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Нет активного рейса']);
            return;
        }

        // Проверяем что зона подходит для породы
        $rockId = $trip->rock_id;
        if ($rockId && !$zone->rocks->contains('id', $rockId)) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => "Зона {$zone->name_zone} не принимает эту породу"
            ]);
            return;
        }

        // Проверяем вместимость зоны
        if ($zone->volume >= $zone->capacity) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => "Зона {$zone->name_zone} заполнена"
            ]);
            return;
        }

        try {
            // Обновляем trip и mining_order
            $trip->update([
                'zone_id' => $zone->id,
                'dump_id' => $zone->dump_id,
            ]);

            if ($trip->miningOrder) {
                $trip->miningOrder->update([
                    'zone_id' => $zone->id,
                ]);
            }

            // Отправляем грузовик в transporting
            $truck->update(['status' => 'transporting']);

            // Уведомляем водителя о назначении зоны
            event(new \App\Events\LoadingCompleted(
                $truck,
                $zone->name_zone,
                $zone->dump->name_dump
            ));

            // Уведомляем диспетчера (для обновления UI)
            event(new \App\Events\DispatcherNotification(
                $truck->id,
                'zone_assigned',
                [
                    'trip_id' => $trip->id,
                    'zone_name' => $zone->name_zone,
                    'dump_name' => $zone->dump->name_dump,
                    'message' => "Зона назначена: {$truck->number} → {$zone->dump->name_dump} - {$zone->name_zone}",
                ]
            ));

            $this->loadData();

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => "Зона назначена: {$truck->number} → {$zone->dump->name_dump} - {$zone->name_zone}",
            ]);

            Log::info("Dispatcher assigned zone for waiting truck", [
                'truck_id' => $truckId,
                'zone_id' => $zoneId,
                'trip_id' => $trip->id,
            ]);

        } catch (\Exception $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => $e->getMessage()]);
            Log::error('assignZoneToWaitingTruck error', ['message' => $e->getMessage()]);
        }
    }

    /**
     * Отменить погрузку для ожидающего грузовика
     */
    public function cancelWaitingLoad(int $truckId): void
    {
        $truck = Truck::find($truckId);

        if (!$truck) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Самосвал не найден']);
            return;
        }

        $trip = TruckTrip::where('truck_id', $truckId)
            ->whereNull('completed_at')
            ->latest()
            ->first();

        try {
            if ($trip) {
                // Завершаем рейс с нулевым объёмом (разгрузка отменена)
                $trip->update([
                    'completed_at' => now(),
                    'load_volume' => 0,
                ]);
            }

            // Переводим в свободный статус
            $truck->update([
                'status' => 'free',
                'current_load' => null,
            ]);

            // Уведомляем водителя об отмене
            if ($truck->driver_id) {
                event(new \App\Events\DriverRouteUpdated(
                    $truck->driver_id,
                    [
                        'truck_id' => $truck->id,
                        'action' => 'load_cancelled',
                        'message' => "Погрузка отменена диспетчером. Вернитесь в отстой.",
                    ]
                ));
            }

            $this->loadData();

            $this->dispatch('notify', [
                'type' => 'warning',
                'message' => "Погрузка отменена: {$truck->number}",
            ]);

            Log::info("Dispatcher cancelled waiting load", [
                'truck_id' => $truckId,
                'trip_id' => $trip?->id,
            ]);

        } catch (\Exception $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => $e->getMessage()]);
            Log::error('cancelWaitingLoad error', ['message' => $e->getMessage()]);
        }
    }

    /**
     * Получить доступные зоны для породы
     */
    public function getAvailableZonesForRock(int $rockId): array
    {
        return Zone::where('delivery', true)
            ->whereHas('rocks', fn($q) => $q->where('rocks.id', $rockId))
            ->whereRaw('volume < capacity')
            ->with(['dump', 'rocks'])
            ->orderBy('volume', 'asc')
            ->get()
            ->map(fn($z) => [
                'id' => $z->id,
                'name' => $z->name_zone,
                'dump_name' => $z->dump->name_dump,
                'volume' => $z->volume,
                'capacity' => $z->capacity,
                'fill' => $z->capacity > 0 ? round($z->volume / $z->capacity * 100) : 0,
            ])
            ->toArray();
    }

    public function render()
    {
        return view('livewire.main-dispatcher-panel');
    }
}
