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
use App\Services\MinerStatusService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;


#[Layout('components.layouts.app')] 
#[Title('Панель диспетчера')] 

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

    // Смена статуса забоя
    public ?int $editMinerStatusId = null;
    public ?string $editMinerStatusNew = null;

    // Фильтры простоев
    public string $pausePeriod = 'shift';
    public array $pauseTypes = [];
    public array $minerPauseTypes = []; // Фильтр по статусам забоев

    // Порода для загруженного грузовика
    public ?int $loadedTruckRockId = null;

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
                    'zone',
                    'zone.rocks',
                    'miner.rocks',
                    'miner.currentRock',
                    'dump',
                    'miningOrder.rock',
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
                    'miner_rock_name' => $trip->miner?->rocks?->first()?->name_rock,
                    '--- ZONE ROCK ---' => '---',
                    'zone_rock_name' => $trip->zone?->rocks?->first()?->name_rock,
                ]);
            }
        }

        $this->miners = Miner::with(['rocks', 'currentRock'])->get();

        $this->dumps = Dump::with(['zones.rocks'])->get();

        $this->zones = Zone::with(['dump', 'rocks'])->get();

        $this->rocks = Rock::all();

        $this->orders = MiningOrder::with(['miner.rocks', 'dump', 'zone.rocks', 'rock'])
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
            Log::info('Previous trip completed before new assignment', [
                'truck_id' => $truck->id,
                'trip_id' => $activeTrip->id
            ]);
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

    /**
     * Назначить зону разгрузки уже загруженному грузовику
     */
    private function assignZoneToLoadedTruck(Truck $truck): void
    {
        $trip = $truck->trips->first();

        if (!$trip) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Нет активного рейса у грузовика']);
            return;
        }

        Log::info('assignZoneToLoadedTruck START', [
            'truck_id' => $truck->id,
            'truck_status' => $truck->status,
            'trip_id' => $trip->id,
            'trip_zone_id' => $trip->zone_id,
            'trip_dump_id' => $trip->dump_id,
            'trip_rock_id' => $trip->rock_id,
            'selected_zone_id' => $this->selectedZoneId,
        ]);

        $zone = $this->selectedZoneId ? Zone::with('dump')->find($this->selectedZoneId) : null;

        // Определяем породу: сначала из trip->rock, потом из miner->currentRock
        $rock = $trip->rock;
        if (!$rock && $trip->miner) {
            $rock = $trip->miner->currentRock;
        }

        Log::info('assignZoneToLoadedTruck rock & zone', [
            'rock_id' => $rock?->id,
            'rock_name' => $rock?->name_rock,
            'zone_from_selection' => $zone?->id,
            'zone_name' => $zone?->name_zone,
        ]);

        // Если зона не выбрана, ищем автоматически на ВСЕХ отвалах
        if (!$zone && $rock) {
            // Сначала на текущем отвале
            $zone = $this->routeService->selectZoneForRock($trip->dump_id, $rock->id);
            
            // Если не нашли - ищем на всех отвалах
            if (!$zone) {
                $zone = \App\Models\Zone::with('dump')
                    ->where('delivery', true)
                    ->whereRaw('volume < capacity')
                    ->whereHas('rocks', fn($q) => $q->where('rocks.id', $rock->id))
                    ->orderBy('volume', 'asc')
                    ->first();
                
                Log::info('Zone found on all dumps', [
                    'zone_id' => $zone?->id,
                    'zone_name' => $zone?->name_zone,
                    'dump_id' => $zone?->dump_id,
                ]);
            }
        }

        if (!$zone) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Выберите зону разгрузки']);
            return;
        }

        try {
            // Обновляем зону, породу и отвал в текущем рейсе
            $trip->update([
                'zone_id' => $zone->id,
                'dump_id' => $zone->dump_id, // Может измениться если зона на другом отвале
                'rock_id' => $rock?->id ?? $trip->rock_id,
            ]);

            Log::info('Trip updated', [
                'trip_id' => $trip->id,
                'zone_id' => $zone->id,
                'dump_id' => $zone->dump_id,
                'rock_id' => $rock?->id,
            ]);

            // Обновляем mining_order если есть
            if ($trip->miningOrder) {
                $trip->miningOrder->update([
                    'zone_id' => $zone->id,
                    'dump_id' => $zone->dump_id,
                    'rock_id' => $rock?->id ?? $trip->rock_id,
                ]);
            }

            // Меняем статус на "перевозка" (едет на отвал)
            $truck->update([
                'status' => Truck::STATUS_TRANSPORTING,
                'route_version' => $truck->route_version + 1,
            ]);

            // Завершаем паузу ожидания, если есть
            $waitingPause = $trip->pauses()
                ->where('type', TripPause::TYPE_WAITING_UNLOADING)
                ->whereNull('ended_at')
                ->first();

            if ($waitingPause) {
                $waitingPause->update(['ended_at' => now()]);
                Log::info('Waiting pause ended', [
                    'truck_id' => $truck->id,
                    'pause_id' => $waitingPause->id
                ]);
            }

            // Перезагружаем trip с обновлёнными данными для события
            $trip->refresh();
            $trip->load(['miner', 'dump', 'zone', 'rock']);
            
            // Отправляем событие водителю с актуальными данными рейса
            event(new DriverRouteUpdated($truck, $trip));
            
            // Отправляем уведомление диспетчеру об изменении статуса
            event(new \App\Events\DispatcherNotification(
                $truck->id,
                $truck->status,
                [
                    'zone_id' => $zone->id,
                    'zone_name' => $zone->name_zone,
                    'dump_name' => $zone->dump?->name_dump,
                    'rock_name' => $trip->rock?->name_rock,
                ]
            ));

            $this->reset(['selectedTruckId', 'selectedMinerId', 'selectedOrderId', 'selectedZoneId', 'loadedTruckRockId']);
            $this->availableOrders = [];
            $this->availableZones = [];
            $this->loadData();

            $dumpName = $zone->dump?->name_dump ?? '—';
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => "Зона назначена: {$truck->number} → {$dumpName} - {$zone->name_zone}",
            ]);

        } catch (\Exception $e) {
            Log::error('assignZoneToLoadedTruck error', ['error' => $e->getMessage()]);
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
        // Доступны для назначения = все кроме breakdown
        return $this->trucks->where('status', '!=', 'breakdown')->count();
    }

    public function getWorkingTrucksCountProperty(): int
    {
        // В работе = все кроме free, completed, breakdown
        return $this->trucks->whereNotIn('status', ['free', 'completed', 'breakdown'])->count();
    }

    public function getActiveMinersCountProperty(): int
    {
        return $this->miners->where('status', 'active')->count();
    }

    public function getBreakdownCountProperty(): int
    {
        return $this->trucks->where('status', 'breakdown')->count();
    }

    /**
     * Количество забоев в поломке
     */
    public function getMinerBreakdownCountProperty(): int
    {
        return $this->miners->where('status', 'breakdown')->count();
    }

    /**
     * Количество забоев в задержке (все кроме active)
     */
    public function getMinerDelayedCountProperty(): int
    {
        return $this->miners->where('status', '!=', 'active')->count();
    }

    public function getFreeTrucksProperty()
    {
        // Грузовики, которым можно назначить маршрут: все кроме breakdown
        return $this->trucks->where('status', '!=', 'breakdown');
    }

    /**
     * Грузовики уже загруженные (нужна только зона разгрузки)
     */
    public function getLoadedTruckStatuses(): array
    {
        return ['waiting_unloading', 'transporting'];
    }

    /**
     * Проверить, что выбранный грузовик уже загружен
     */
    public function isSelectedTruckLoaded(): bool
    {
        if (!$this->selectedTruckId) {
            return false;
        }

        // Загружаем грузовик заново с актуальными данными
        $truck = Truck::with(['trips' => function ($q) {
            $q->whereNull('completed_at')
                ->with(['rock', 'miner.currentRock'])
                ->latest();
        }])->find($this->selectedTruckId);

        if (!$truck) {
            return false;
        }

        $isLoaded = in_array($truck->status, $this->getLoadedTruckStatuses());

        // Отладка
        if ($isLoaded) {
            $trip = $truck->trips->first();
            Log::info('isSelectedTruckLoaded DEBUG', [
                'truck_id' => $truck->id,
                'truck_status' => $truck->status,
                'trip_id' => $trip?->id,
                'trip_rock_id' => $trip?->rock_id,
                'trip_rock_name' => $trip?->rock?->name_rock,
                'miner_id' => $trip?->miner_id,
                'miner_current_rock_id' => $trip?->miner?->current_rock_id,
            ]);
        }

        return $isLoaded;
    }

    /**
     * Получить данные загруженного грузовика для отображения
     */
    public function getLoadedTruckInfoProperty(): ?array
    {
        if (!$this->selectedTruckId) {
            return null;
        }

        $truck = Truck::with(['trips' => function ($q) {
            $q->whereNull('completed_at')
                ->with(['rock', 'miner.currentRock', 'dump'])
                ->latest();
        }])->find($this->selectedTruckId);

        if (!$truck || !in_array($truck->status, $this->getLoadedTruckStatuses())) {
            return null;
        }

        $trip = $truck->trips->first();
        if (!$trip) {
            return null;
        }

        // Определяем породу
        $rock = $trip->rock;
        if (!$rock && $trip->miner) {
            $rock = $trip->miner->currentRock;
        }

        return [
            'truck' => $truck,
            'trip' => $trip,
            'rock' => $rock,
            'rock_id' => $rock?->id,
            'miner_name' => $trip->miner?->name_miner,
            'rock_name' => $rock?->name_rock,
            'load_volume' => $trip->load_volume ?? $truck->current_load,
            'dump_id' => $trip->dump_id,
        ];
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
                ->whereHas('rocks', fn($q) => $q->where('rocks.id', $rock->id))
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

            Log::info('updatedLoadedTruckRockId - available zones', [
                'rock_id' => $rock->id,
                'rock_name' => $rock->name_rock,
                'zones_count' => count($this->availableZones),
                'zones' => $this->availableZones,
            ]);
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
                        ->whereHas('rocks', fn($q) => $q->where('rocks.id', $rock->id))
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

    /**
     * Активные экскаваторы с породой в забое
     */
    public function getActiveMinersWithRockProperty()
    {
        return $this->miners
            ->filter(fn($m) => $m->active && ($m->currentRock || $m->rocks->isNotEmpty()))
            ->values();
    }

    #[On('truck-status-changed')]
    public function onTruckStatusChanged(array $data): void
    {
        Log::info('MainDispatcherPanel: truck-status-changed received', $data);
        $this->loadData();
    }

    #[On('refresh-dispatcher-data')]
    public function onRefreshData(): void
    {
        Log::info('MainDispatcherPanel: refresh-dispatcher-data event received');
        $this->loadData();
    }

    #[On('echo:dispatcher,.truck-updated')]
    public function onTruckUpdated(): void
    {
        Log::info('MainDispatcherPanel: truck-updated received via Echo');
        $this->loadData();
    }

    #[On('echo:dispatcher,.miner-productivity-updated')]
    public function onMinerProductivityUpdated(array $data): void
    {
        Log::info('MainDispatcherPanel: miner-productivity-updated received', $data);
        
        // Обновляем данные о забоях
        $this->miners = Miner::with(['rocks', 'currentRock'])->get();
        
        // Отправляем уведомление
        $this->dispatch('notify', [
            'type' => 'info',
            'message' => "Обновлена производительность забоя (цель: {$data['target_load_time']} мин)",
        ]);
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

    /**
     * Открыть модальное окно смены статуса забоя
     */
    public function openMinerStatusModal(int $minerId): void
    {
        $miner = Miner::find($minerId);
        if ($miner) {
            $this->editMinerStatusId = $minerId;
            $this->editMinerStatusNew = $miner->status;
        }
    }

    /**
     * Закрыть модальное окно смены статуса забоя
     */
    public function closeMinerStatusModal(): void
    {
        $this->editMinerStatusId = null;
        $this->editMinerStatusNew = null;
    }

    /**
     * Сохранить новый статус забоя
     */
    public function setMinerStatus(): void
    {
        if (!$this->editMinerStatusId || !$this->editMinerStatusNew) {
            return;
        }

        $miner = Miner::find($this->editMinerStatusId);
        if (!$miner) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Забой не найден']);
            return;
        }

        try {
            $statusService = app(MinerStatusService::class);
            $result = $statusService->changeStatus($miner, $this->editMinerStatusNew, Auth::id());

            if ($result['success']) {
                $this->closeMinerStatusModal();
                $this->loadData();

                $this->dispatch('notify', [
                    'type' => in_array($this->editMinerStatusNew, Miner::STATUSES_DELAYED) ? 'warning' : 'success',
                    'message' => "Статус забоя {$miner->name_miner}: {$miner->getStatusLabel()}",
                ]);
            } else {
                $this->dispatch('notify', ['type' => 'error', 'message' => $result['message']]);
            }
        } catch (\Exception $e) {
            Log::error('setMinerStatus failed', ['error' => $e->getMessage()]);
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Ошибка: ' . $e->getMessage()]);
        }
    }

    /**
     * Список всех статусов забоя
     */
    public function getMinerStatusesProperty(): array
    {
        return Miner::getAllStatuses();
    }

    /**
     * Список типов пауз забоя для фильтра
     */
    public function getMinerDelayStatusesProperty(): array
    {
        return \App\Models\MinerPause::types();
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
        $periodLabel = 'За смену';
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
                // Для смены берём последние 12 часов (покрывает ночную смену)
                $shiftStart = now()->subHours(12);
                if ($shiftStart->isYesterday()) {
                    $query->where('started_at', '>=', $shiftStart);
                } else {
                    $query->where('started_at', '>=', now()->startOfDay());
                }
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
     * Статистика по простоям забоев
     */
    public function getMinerDelaysProperty(): array
    {
        $query = \App\Models\MinerPause::with(['miner'])
            ->orderBy('started_at', 'desc');

        // Период
        $periodLabel = 'За смену';
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
                // Для смены берём последние 12 часов (покрывает ночную смену)
                // или от начала дня если сейчас день
                $shiftStart = now()->subHours(12);
                if ($shiftStart->isYesterday()) {
                    // Если смена началась вчера, берём с этого времени
                    $query->where('started_at', '>=', $shiftStart);
                } else {
                    // Иначе берём с начала дня
                    $query->where('started_at', '>=', now()->startOfDay());
                }
                $periodLabel = 'За смену';
        }

        // Фильтр по типу статуса
        if (!empty($this->minerPauseTypes)) {
            $query->whereIn('type', $this->minerPauseTypes);
        }

        $pauses = $query->get();

        // Группируем по типу
        $byStatus = $pauses->groupBy('type')->map(function ($group, $type) {
            $totalSeconds = $group->sum(function ($p) {
                return $p->getCurrentDuration();
            });
            return [
                'status' => $type,
                'label' => \App\Models\MinerPause::typeLabel($type),
                'count' => $group->count(),
                'total_minutes' => round($totalSeconds / 60),
                'total_formatted' => $this->formatMinutes(round($totalSeconds / 60)),
            ];
        })->values();

        $totalSeconds = $pauses->sum(fn($p) => $p->getCurrentDuration());
        $totalMinutes = round($totalSeconds / 60);

        return [
            'pauses' => $pauses,
            'total_count' => $pauses->count(),
            'total_minutes' => $totalMinutes,
            'total_formatted' => $this->formatMinutes($totalMinutes),
            'active_count' => $pauses->whereNull('ended_at')->count(),
            'by_status' => $byStatus,
            'period_label' => $periodLabel,
        ];
    }

    private function formatMinutes(int $minutes): string
    {
        $hours = floor($minutes / 60);
        $mins = $minutes % 60;
        
        if ($hours > 0) {
            return "{$hours}ч {$mins}м";
        }
        return "{$mins}м";
    }

    public function render()
    {
        return view('livewire.main-dispatcher-panel');
    }
}