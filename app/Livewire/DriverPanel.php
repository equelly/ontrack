<?php

namespace App\Livewire;

use App\Domain\TruckStatus;
use App\Models\Truck;
use App\Models\TruckTrip;
use App\Models\Zone;
use App\Services\TruckStatusService;
use App\Services\RouteAssignmentService;
use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\Log;

class DriverPanel extends Component
{
    public Truck $truck;
    public ?TruckTrip $currentTrip = null;
    public array $stats = [];
    public string $statusColor = 'secondary';
    public string $statusLabel = '';

    // Время начала рейса для таймера
    public ?string $tripStartedAt = null;

    // Модальные окна
    public bool $showZoneModal = false;
    public bool $showDelayModal = false;
    public string $delayReason = 'traffic';
    public int $delayMinutes = 15;
    public $availableZones = [];

    public function mount(Truck $truck)
    {
        $this->truck = $truck;
        $this->loadData();
    }

    protected function loadData(): void
    {
        $this->truck->refresh();

        $this->currentTrip = TruckTrip::where('truck_id', $this->truck->id)
            ->whereNull('completed_at')
            ->with(['miner.rocks', 'dump', 'zone.rocks', 'miningOrder.rock', 'rock'])
            ->latest()
            ->first();

        // Логирование данных водителя
        if ($this->currentTrip) {
            Log::info('DRIVER PANEL DATA', [
                'truck_id' => $this->truck->id,
                'truck_number' => $this->truck->number,
                'truck_status' => $this->truck->status,
                'trip_id' => $this->currentTrip->id,
                'trip_miner_id' => $this->currentTrip->miner_id,
                'trip_miner_name' => $this->currentTrip->miner?->name_miner,
                'trip_dump_id' => $this->currentTrip->dump_id,
                'trip_dump_name' => $this->currentTrip->dump?->name_dump,
                'trip_zone_id' => $this->currentTrip->zone_id,
                'trip_zone_name' => $this->currentTrip->zone?->name_zone,
                'trip_rock_id' => $this->currentTrip->rock_id,
                'trip_rock_name' => $this->currentTrip->rock?->name_rock,
                'miner_rock_name' => $this->currentTrip->miner?->rocks?->first()?->name_rock,
                'zone_rock_name' => $this->currentTrip->zone?->rocks?->first()?->name_rock,
            ]);
        }

        $this->statusColor = TruckStatus::color($this->truck->status);
        $this->statusLabel = TruckStatus::label($this->truck->status);

        // Устанавливаем время начала рейса для таймера
        $this->tripStartedAt = $this->currentTrip?->started_at?->toIso8601String();

        $this->stats = [
            'total_trips' => TruckTrip::where('truck_id', $this->truck->id)
                ->whereNotNull('completed_at')
                ->count(),
            'today_trips' => TruckTrip::where('truck_id', $this->truck->id)
                ->whereNotNull('completed_at')
                ->whereDate('completed_at', today())
                ->count(),
            'total_volume' => TruckTrip::where('truck_id', $this->truck->id)
                ->whereNotNull('completed_at')
                ->sum('load_volume'),
        ];
    }

    // =========================================
    // ДЕЙСТВИЯ ВОДИТЕЛЯ
    // =========================================

    public function assignRoute(): void
    {
        try {
            $routeService = app(RouteAssignmentService::class);
            $routeService->assignForTruck($this->truck);

            $this->loadData();


        } catch (\Exception $e) {
            Log::error('Route assignment failed', ['error' => $e->getMessage()]);
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Не удалось назначить маршрут: ' . $e->getMessage(),
            ]);
        }
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
            $statusService = app(TruckStatusService::class);
            $statusService->changeStatus($this->truck, 'completed');
            $this->loadData();
        } catch (\Exception $e) {
            Log::error('Complete trip failed', ['error' => $e->getMessage()]);
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
        } catch (\Exception $e) {
            Log::error('Report breakdown failed', ['error' => $e->getMessage()]);
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function reportBreakdownResolved(): void
    {
        try {
            $statusService = app(TruckStatusService::class);
            $statusService->changeStatus($this->truck, 'free');
            $this->loadData();
        } catch (\Exception $e) {
            Log::error('Breakdown resolved failed', ['error' => $e->getMessage()]);
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => $e->getMessage(),
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
    // REAL-TIME EVENTS (от Echo через Livewire)
    // =========================================
        // Слушаем общий канал dispatcher для обновления при любых изменениях статуса
    #[On('echo:dispatcher,.truck-updated')]
    public function onTruckUpdated(): void
    {
        Log::info('DispatcherNotification received in DriverPanel');
        $this->loadData();
    }

    // DriverRouteUpdated использует public Channel, не private!
    #[On('echo:driver.{truck.id},.route.updated')]
    public function onDriverRouteUpdated(): void
    {
        Log::info('DriverRouteUpdated event received');
        $this->loadData();
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Назначен новый маршрут!',
        ]);
    }

    // LoadingCompleted использует PrivateChannel
    #[On('echo-private:truck.{truck.id},.loading.completed')]
    public function onLoadingCompleted(): void
    {
        Log::info('LoadingCompleted event received');
        $this->loadData();
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Погрузка завершена! Можете отправляться.',
        ]);
    }

    // ZoneChanged использует PrivateChannel('driver.{truck.id}') с broadcastAs 'zone.changed'
    #[On('echo-private:driver.{truck.id},.zone.changed')]
    public function onZoneChangedEcho(): void
    {
        Log::info('ZoneChanged event received via Echo');
        $this->loadData();
        $this->dispatch('notify', [
            'type' => 'info',
            'message' => 'Зона изменена!',
        ]);
    }

    public function render()
    {
        return view('livewire.driver-panel');
    }
}
