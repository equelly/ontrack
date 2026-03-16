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
        // Загружаем trucks с trips - используем latest() как в DriverPanel!
        $this->trucks = Truck::with(['truckModel', 'driver', 'trips' => function ($q) {
            $q->whereNull('completed_at')
                ->with([
                    'rock',
                    'zone.rocks',
                    'miner.rocks',
                    'dump',
                ])
                ->latest(); // ВАЖНО: берём самый свежий trip
        }])->get();

        // Полное логирование всех данных рейса
        foreach ($this->trucks as $truck) {
            // Берём первый trip (теперь они отсортированы по latest())
            $trip = $truck->trips->first();
            if ($trip) {
                Log::info('DISPATCHER: FULL TRUCK DATA', [
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
                    '--- ALL TRIPS ---' => '---',
                    'trips_count' => $truck->trips->count(),
                ]);
            }
        }

        $this->miners = Miner::with(['rocks'])
            ->where('active', true)
            ->get();

        $this->dumps = Dump::with(['zones.rocks'])->get();

        $this->zones = Zone::with(['dump', 'rocks'])->get();

        $this->rocks = Rock::all();

        $this->orders = MiningOrder::with(['miner.rocks', 'dump', 'zone.rocks', 'rock', 'truck'])
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

    #[On('truck-status-changed')]
    public function onTruckStatusChanged(): void
    {
        Log::info('Dispatcher: truck-status-changed received');
        $this->loadData();
    }

    #[On('echo:dispatcher,.truck-updated')]
    public function onTruckUpdated(): void
    {
        Log::info('Dispatcher: truck-updated received via Echo');
        $this->loadData();
    }

    public function render()
    {
        return view('livewire.main-dispatcher-panel');
    }
}
