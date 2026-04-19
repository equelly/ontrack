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
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;


#[Layout('components.layouts.app')]
#[Title('Панель водителя')]

class DriverPanel extends Component
{
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
    public string $delayReason = 'traffic';
    public int $delayMinutes = 15;
    public $availableZones = [];

    public function mount(): void
    {
        $this->loadTrucks();
        
        // Пробуем восстановить выбранный грузовик из cookie
        $savedTruckId = request()->cookie('selected_truck_id');
        if ($savedTruckId) {
            $this->selectedTruckId = (int) $savedTruckId;
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
        
        if ($this->truck) {
            // Привязываем грузовик к водителю если нужно
            if ($this->truck->driver_id !== auth()->id()) {
                $this->truck->update(['driver_id' => auth()->id()]);
            }
            
            $this->loadData();
            
            // Сохраняем в cookie
            $this->dispatch('set-cookie', [
                'name' => 'selected_truck_id',
                'value' => $this->selectedTruckId,
                'days' => 30,
            ]);

            $this->dispatch('truck-selected', ['truck_id' => $this->truck->id]);
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
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Назначен новый маршрут!',
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

    public function render()
    {
        return view('livewire.driver-panel');
    }
}
