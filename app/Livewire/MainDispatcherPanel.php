<?php

namespace App\Livewire;

use App\Models\Truck;
use App\Models\Miner;
use App\Models\Dump;
use App\Models\Zone;
use App\Models\Rock;
use App\Models\TruckTrip;
use App\Models\MiningOrder;
use App\Events\DriverRouteUpdated;
use App\Services\RouteAssignmentService;
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

    public ?int $selectedTruckId = null;
    public ?int $selectedMinerId = null;
    public ?int $selectedOrderId = null;
    public ?int $selectedZoneId = null;

    public $availableOrders = [];
    public $availableZones = [];

    // Фильтры для статистики простоев
    public string $pausePeriod = 'shift'; // shift, today, week, month
    public array $pauseTypes = []; // массив выбранных типов (пусто = все)

    // Активная вкладка
    public string $activeTab = 'trucksTab';

    protected RouteAssignmentService $routeService;

    public function boot(RouteAssignmentService $routeService)
    {
        $this->routeService = $routeService;
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
                    'miner_rock_name' => $trip->miner?->rocks?->first()?->name_rock,
                    '--- ZONE ROCK ---' => '---',
                    'zone_rock_name' => $trip->zone?->rocks?->first()?->name_rock,
                ]);
            }
        }

        $this->miners = Miner::with(['rocks'])->get();

        $this->dumps = Dump::with(['zones.rocks'])->get();

        $this->zones = Zone::with(['dump', 'rocks'])->get();

        $this->rocks = Rock::all();

        $this->orders = MiningOrder::with(['miner.rocks', 'dump', 'zone.rocks', 'rock', 'truck'])
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
            $this->availableOrders = MiningOrder::with(['dump', 'zone'])
                ->where('miner_id', $value)
                ->where('active', true)
                ->get()
                ->map(fn($o) => [
                    'id' => $o->id,
                    'dump_name' => $o->dump?->name_dump,
                    'distance' => $o->distance_km,
                ])
                ->toArray();
        }
    }

    public function updatedSelectedOrderId(?int $value): void
    {
        $this->selectedZoneId = null;
        $this->availableZones = [];

        if ($value) {
            $order = MiningOrder::find($value);
            if ($order) {
                $rock = $order->rock ?? $order->miner?->rocks?->first();

                $this->availableZones = Zone::where('dump_id', $order->dump_id)
                    ->where('delivery', true)
                    ->when($rock, fn($q) => $q->whereHas('rocks', fn($q) => $q->where('rocks.id', $rock->id)))
                    ->whereRaw('volume < capacity')
                    ->get()
                    ->map(fn($z) => [
                        'id' => $z->id,
                        'name' => $z->name_zone,
                        'volume' => $z->volume,
                        'capacity' => $z->capacity,
                    ])
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

        if ($truck->status !== 'free') {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Самосвал не свободен']);
            return;
        }

        if (!$zone) {
            $zone = $this->routeService->selectZone($order);
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

    public function toggleMinerStatus(int $minerId): void
    {
        $miner = Miner::find($minerId);

        if (!$miner) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Забой не найден']);
            return;
        }

        $newStatus = !$miner->active;
        
        // Если деактивируем - проверяем есть ли грузовики в работе
        if (!$newStatus) {
            $trucksInWork = Truck::whereHas('trips', function ($q) use ($minerId) {
                $q->where('miner_id', $minerId)
                  ->whereNull('completed_at');
            })->whereIn('status', ['to_miner', 'loading'])->count();

            if ($trucksInWork > 0) {
                $this->dispatch('notify', [
                    'type' => 'warning',
                    'message' => "Невозможно деактивировать: {$trucksInWork} самосвалов в пути к забою",
                ]);
                return;
            }
        }

        $miner->update([
            'active' => $newStatus,
            'last_updated_at' => now(),
            'last_updated_by' => auth()->id(),
        ]);

        $this->loadData();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => $newStatus 
                ? "Забой {$miner->name_miner} активирован" 
                : "Забой {$miner->name_miner} деактивирован",
        ]);
    }

    public function getFreeTrucksCountProperty(): int
    {
        return $this->trucks->where('status', 'free')->count();
    }

    public function getWorkingTrucksCountProperty(): int
    {
        return $this->trucks->whereNotIn('status', ['free', 'breakdown'])->count();
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
        return $this->trucks->where('status', 'free');
    }

    // =========================================
    // ПЛАНОВАЯ СТАТИСТИКА ПО РАССТОЯНИЯМ
    // =========================================

    public function getPlannedDistanceStatsProperty(): array
    {
        // Активные заказы с расстоянием
        $activeOrders = $this->orders->where('active', true);
        
        // Среднее расстояние по активным заказам
        $avgDistance = $activeOrders->avg('distance_km') ?? 0;
        
        return [
            'avg_distance' => round($avgDistance, 1),
        ];
    }

    // =========================================
    // СТАТИСТИКА ПРОСТОЕВ
    // =========================================

    public function getPausePeriodStart(): \Carbon\Carbon
    {
        return match ($this->pausePeriod) {
            'shift' => $this->getShiftStart(),
            'today' => now()->startOfDay(),
            'week' => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            default => $this->getShiftStart(),
        };
    }

    public function getShiftStart(): \Carbon\Carbon
    {
        $now = now();
        $hour = $now->hour;
        $minute = $now->minute;

        if ($hour >= 7 && $hour < 19) {
            if ($hour === 7 && $minute < 30) {
                return $now->copy()->subDay()->setTime(19, 30);
            }
            return $now->copy()->setTime(7, 30);
        } elseif ($hour >= 19) {
            if ($hour === 19 && $minute < 30) {
                return $now->copy()->setTime(7, 30);
            }
            return $now->copy()->setTime(19, 30);
        } else {
            return $now->copy()->subDay()->setTime(19, 30);
        }
    }

    public function getPauseStatsProperty(): array
    {
        $periodStart = $this->getPausePeriodStart();

        $query = \App\Models\TripPause::with(['truck', 'trip'])
            ->where('started_at', '>=', $periodStart);

        // Фильтр по нескольким типам
        if (!empty($this->pauseTypes)) {
            $query->whereIn('type', $this->pauseTypes);
        }

        $pauses = $query->orderBy('started_at', 'desc')->get();

        // Общая статистика по типам
        $byType = $pauses->groupBy('type')->map(function ($items, $type) {
            $totalSeconds = $items->sum(function ($pause) {
                return $pause->duration_seconds ?? $pause->getCurrentDuration();
            });

            return [
                'type' => $type,
                'label' => \App\Models\TripPause::typeLabel($type),
                'count' => $items->count(),
                'total_seconds' => $totalSeconds,
                'total_formatted' => $this->formatDuration($totalSeconds),
            ];
        })->sortByDesc('total_seconds');

        // Общее время
        $totalSeconds = $pauses->sum(function ($pause) {
            return $pause->duration_seconds ?? $pause->getCurrentDuration();
        });

        // Активные (не завершённые)
        $activeCount = $pauses->whereNull('ended_at')->count();

        return [
            'pauses' => $pauses,
            'by_type' => $byType,
            'total_count' => $pauses->count(),
            'total_seconds' => $totalSeconds,
            'total_formatted' => $this->formatDuration($totalSeconds),
            'active_count' => $activeCount,
            'period_label' => $this->getPeriodLabel(),
        ];
    }

    protected function formatDuration(int $seconds): string
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);

        if ($hours > 0) {
            return sprintf('%d ч %d мин', $hours, $minutes);
        }
        return sprintf('%d мин', $minutes);
    }

    protected function getPeriodLabel(): string
    {
        return match ($this->pausePeriod) {
            'shift' => 'За смену',
            'today' => 'За сегодня',
            'week' => 'За неделю',
            'month' => 'За месяц',
            default => 'За период',
        };
    }

    public function updatedPausePeriod(): void
    {
        $this->activeTab = 'pausesTab';
    }

    public function updatedPauseTypes(): void
    {
        $this->activeTab = 'pausesTab';
    }

    public function setActiveTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    // =========================================
    // ПРИНУДИТЕЛЬНАЯ СМЕНА СТАТУСА
    // =========================================

    public ?int $forceStatusTruckId = null;
    public ?string $forceStatusNew = null;

    public function openForceStatusModal(int $truckId): void
    {
        $this->forceStatusTruckId = $truckId;
        
        // Автоматически выбираем предыдущий статус если есть
        $truck = Truck::find($truckId);
        $this->forceStatusNew = $truck?->before_breakdown;
    }

    public function closeForceStatusModal(): void
    {
        $this->forceStatusTruckId = null;
        $this->forceStatusNew = null;
    }

    public function forceChangeStatus(): void
    {
        if (!$this->forceStatusTruckId || !$this->forceStatusNew) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Выберите статус']);
            return;
        }

        $truck = Truck::find($this->forceStatusTruckId);
        if (!$truck) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Самосвал не найден']);
            return;
        }

        try {
            $oldStatus = $truck->status;
            
            // Завершаем активную паузу если есть
            $trip = TruckTrip::where('truck_id', $truck->id)
                ->whereNull('completed_at')
                ->latest()
                ->first();

            if ($trip) {
                // Завершаем все активные паузы
                foreach ($trip->pauses()->whereNull('ended_at')->get() as $pause) {
                    $pause->end();
                }

                // Если переводим в free - завершаем рейс
                if ($this->forceStatusNew === 'free') {
                    $trip->update(['completed_at' => now()]);
                    
                    // Освобождаем заказ
                    MiningOrder::where('truck_id', $truck->id)
                        ->update(['truck_id' => null, 'zone_id' => null]);
                }
            }

            $truck->update([
                'status' => $this->forceStatusNew,
                'before_breakdown' => null,
                'pause_started_at' => null,
            ]);

            Log::info("Dispatcher forced status change", [
                'truck_id' => $truck->id,
                'truck_number' => $truck->number,
                'old_status' => $oldStatus,
                'new_status' => $this->forceStatusNew,
            ]);

            // Уведомляем диспетчера об изменении
            event(new \App\Events\DispatcherNotification(
                $truck->id,
                $this->forceStatusNew,
                ['forced' => true, 'from' => $oldStatus]
            ));

            $this->loadData();
            $this->closeForceStatusModal();

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => "Статус {$truck->number} изменён: {$oldStatus} → {$this->forceStatusNew}",
            ]);

        } catch (\Exception $e) {
            Log::error('Force status change failed', ['error' => $e->getMessage()]);
            $this->dispatch('notify', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function getAvailableStatusesProperty(): array
    {
        return [
            'free' => 'Свободен',
            'to_miner' => 'К забою',
            'loading' => 'Погрузка',
            'transporting' => 'Перевозка',
            'unloading' => 'Разгрузка',
            'delayed' => 'Задержка',
            'breakdown' => 'Поломка',
        ];
    }

    #[On('truck-status-changed')]
    public function onTruckStatusChanged(array $data): void
    {
        $this->loadData();
    }

    #[On('echo:dispatcher,.truck-updated')]
    public function onTruckUpdated(): void
    {
        Log::info('MainDispatcherPanel: truck-updated received via Echo');
        $this->loadData();
    }

    public function render()
    {
        return view('livewire.main-dispatcher-panel');
    }
}
