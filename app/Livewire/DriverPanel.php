<?php

namespace App\Livewire;

use App\Domain\TruckStatus;
use App\Models\Truck;
use App\Models\TruckTrip;
use App\Models\TripPause;
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
    public ?TripPause $activePause = null;
    public array $stats = [];
    public string $statusColor = 'secondary';
    public string $statusLabel = '';

    // Данные для таймера
    public ?string $tripStartedAt = null;
    public ?string $pauseStartedAt = null;
    public ?string $pauseType = null;
    public int $totalPauseSeconds = 0;

    // Модальные окна
    public bool $showZoneModal = false;
    public bool $showDelayModal = false;
    public bool $showBreakdownModal = false; // Для выбора: продолжить или отменить
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
            ->with(['miner.currentRock', 'miner.rocks', 'dump', 'zone.rocks', 'miningOrder.rock', 'rock', 'pauses'])
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

        Log::info('DriverPanel TIMER DATA', [
            'trip_id' => $this->currentTrip?->id,
            'started_at_raw' => $this->currentTrip?->started_at,
            'tripStartedAt_iso' => $this->tripStartedAt,
            'pauseStartedAt' => $this->pauseStartedAt,
            'pauseType' => $this->pauseType,
            'totalPauseSeconds' => $this->totalPauseSeconds,
        ]);

        $this->statusColor = TruckStatus::color($this->truck->status);
        $this->statusLabel = TruckStatus::label($this->truck->status);

        Log::info('DriverPanel loadData', [
            'truck_id' => $this->truck->id,
            'truck_number' => $this->truck->number,
            'truck_status' => $this->truck->status,
            '--- TRIP ---' => '---',
            'trip_id' => $this->currentTrip?->id,
            'trip_miner_id' => $this->currentTrip?->miner_id,
            'trip_miner_name' => $this->currentTrip?->miner?->name_miner,
            'trip_dump_id' => $this->currentTrip?->dump_id,
            'trip_dump_name' => $this->currentTrip?->dump?->name_dump,
            'trip_zone_id' => $this->currentTrip?->zone_id,
            'trip_zone_name' => $this->currentTrip?->zone?->name_zone,
            'trip_rock_id' => $this->currentTrip?->rock_id,
            'trip_rock_name' => $this->currentTrip?->rock?->name_rock,
            '--- MINER ROCK ---' => '---',
            'miner_rock_name' => $this->currentTrip?->miner?->currentRock?->name_rock ?? $this->currentTrip?->miner?->rocks?->first()?->name_rock,
            '--- ORDER ---' => '---',
            'order_id' => $this->currentTrip?->miningOrder?->id,
            'order_rock_id' => $this->currentTrip?->miningOrder?->rock_id,
            'order_rock_name' => $this->currentTrip?->miningOrder?->rock?->name_rock,
            '--- PAUSE ---' => '---',
            'pause_started_at' => $this->pauseStartedAt,
            'pause_type' => $this->pauseType,
            'total_pause_seconds' => $this->totalPauseSeconds,
        ]);

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

    /**
     * Уйти в отстой
     */
    public function goToStandby(): void
    {
        try {
            $statusService = app(TruckStatusService::class);
            $statusService->changeStatus($this->truck, 'free');
            $this->loadData();

            $this->dispatch('notify', [
                'type' => 'info',
                'message' => 'Вы в отстое',
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

    /**
     * Поломка устранена - продолжить рейс
     */
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

    /**
     * Поломка устранена - отменить рейс
     */
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

    /**
     * Возобновление после задержки
     */
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

    #[On('echo:dispatcher,truck-updated')]
    public function onTruckUpdated(): void
    {
        Log::info('DispatcherNotification received in DriverPanel');
        $this->loadData();
    }

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

    #[On('echo-private:truck.{truck.id},.loading.completed')]
    public function onLoadingCompleted(array $data): void
    {
        Log::info('LoadingCompleted event received', $data);
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
