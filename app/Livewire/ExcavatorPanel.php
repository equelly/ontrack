<?php

namespace App\Livewire;

use App\Models\Miner;
use App\Models\Rock;
use App\Models\Truck;
use App\Models\TruckTrip;
use App\Events\LoadingCompleted;
use App\Services\TruckStatusService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Livewire\Attributes\On;

class ExcavatorPanel extends Component
{
    public ?Miner $miner = null;
    public $miners;
    public $rocks;
    public $trucks;
    public array $stats = [];

    // Выбор экскаватора
    public ?int $selectedMinerId = null;

    // Выбор породы
    public ?int $selectedRockId = null;

    // Объёмы для грузовиков
    public array $volumes = [];

    protected function rules(): array
    {
        return [
            'selectedMinerId' => 'nullable|exists:miners,id',
            'selectedRockId' => 'nullable|exists:rocks,id',
        ];
    }

    public function mount()
    {
        $this->miners = Miner::where('active', true)->orderBy('name_miner')->get();
        $this->rocks = Rock::orderBy('name_rock')->get();

        // Восстанавливаем выбранный экскаватор из cookie или пользователя
        $minerId = request()->cookie('selected_miner') ?? Auth::user()?->miner_id;

        if ($minerId) {
            $this->selectedMinerId = (int) $minerId;

            // Автоматически сохраняем привязку к экскаватору для авторизации канала
            $user = Auth::user();
            if ($user && (int)$user->miner_id !== (int)$minerId) {
                $user->miner_id = $minerId;
                $user->save();
            }

            $this->loadMinerData();
        }
    }

    public function loadMinerData(): void
    {
        if (!$this->selectedMinerId) {
            $this->miner = null;
            $this->trucks = collect();
            $this->stats = [];
            return;
        }

        $this->miner = Miner::with('rocks')->find($this->selectedMinerId);

        if (!$this->miner) {
            return;
        }

        // Грузим грузовики
        $this->trucks = Truck::with(['trips' => function ($q) {
            $q->where('miner_id', $this->miner->id)
                ->whereNull('completed_at')
                ->with(['miningOrder.dump', 'miningOrder.zone', 'miningOrder.rock']);
        }])
            ->whereIn('status', ['to_miner', 'loading', 'waiting_loading'])
            ->whereHas('trips', function ($q) {
                $q->where('miner_id', $this->miner->id)->whereNull('completed_at');
            })
            ->get();

        // Инициализируем объёмы для грузовиков на погрузке
        foreach ($this->trucks as $truck) {
            if (!isset($this->volumes[$truck->id])) {
                $this->volumes[$truck->id] = $truck->load_capacity ?? 30;
            }
        }

        // Статистика
        $this->stats = $this->getShiftStats();
    }

    // =========================================
    // ДЕЙСТВИЯ
    // =========================================

    public function selectMiner(): void
    {
        if (!$this->selectedMinerId) {
            $this->dispatch('notify', [
                'type' => 'warning',
                'message' => 'Выберите экскаватор',
            ]);
            return;
        }

        $miner = Miner::find($this->selectedMinerId);

        if (!$miner || !$miner->active) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Экскаватор не найден или неактивен',
            ]);
            return;
        }

        // Сохраняем привязку оператора к экскаватору
        $user = Auth::user();
        if ($user) {
            $user->miner_id = $miner->id;
            $user->save();
        }

        $this->loadMinerData();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Экскаватор выбран: ' . $miner->name_miner,
        ]);

        // Устанавливаем cookie через JS
        $this->dispatch('set-cookie', [
            'name' => 'selected_miner',
            'value' => $miner->id,
            'days' => 30,
        ]);

        // Отправляем событие для повторной инициализации Echo
        $this->dispatch('miner-selected', [
            'miner_id' => $miner->id,
        ]);
    }

    public function setRock(): void
    {
        if (!$this->selectedRockId) {
            $this->dispatch('notify', [
                'type' => 'warning',
                'message' => 'Выберите породу',
            ]);
            return;
        }

        if (!$this->miner) {
            $this->dispatch('notify', [
                'type' => 'warning',
                'message' => 'Сначала выберите экскаватор',
            ]);
            return;
        }

        $this->miner->rocks()->sync([$this->selectedRockId]);
        $this->miner->update([
            'last_updated_at' => now(),
            'last_updated_by' => Auth::id(),
        ]);

        $this->loadMinerData();

        $rock = Rock::find($this->selectedRockId);
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Порода установлена: ' . ($rock?->name_rock ?? ''),
        ]);
    }

    public function confirmArrival(int $truckId): void
    {
        $truck = Truck::find($truckId);

        if (!$truck || !in_array($truck->status, ['to_miner', 'waiting_loading'])) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Самосвал не ожидает погрузки',
            ]);
            return;
        }

        try {
            $statusService = app(TruckStatusService::class);
            $statusService->changeStatus($truck, 'loading');

            $this->loadMinerData();

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Самосвал ' . $truck->number . ' прибыл на погрузку',
            ]);

        } catch (\Exception $e) {
            Log::error('Confirm arrival failed', ['error' => $e->getMessage()]);
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function completeLoading(int $truckId): void
    {
        $truck = Truck::find($truckId);

        if (!$truck || $truck->status !== 'loading') {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Самосвал не на погрузке',
            ]);
            return;
        }

        $volume = $this->volumes[$truckId] ?? $truck->load_capacity ?? 30;

        try {
            // Получаем активный рейс
            $trip = TruckTrip::where('truck_id', $truck->id)
                ->whereNull('completed_at')
                ->latest()
                ->first();

            if (!$trip) {
                $this->dispatch('notify', [
                    'type' => 'error',
                    'message' => 'Нет активного рейса',
                ]);
                return;
            }

            // Получаем текущую породу из забоя
            $currentRock = $this->miner?->rocks()->first();

            // Обновляем рейс с породой
            $trip->update([
                'load_volume' => $volume,
                'loaded_at' => now(),
                'rock_id' => $currentRock?->id,
            ]);

            // Меняем статус грузовика
            $truck->update([
                'status' => 'transporting',
                'current_load' => $volume,
            ]);

            // Получаем информацию о зоне и дампе
            $zone = $trip->miningOrder?->zone;
            $dump = $trip->miningOrder?->dump;

            // Отправляем уведомление водителю
            event(new LoadingCompleted(
                $truck,
                $zone?->name_zone,
                $dump?->name_dump
            ));

            $this->loadMinerData();

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => "Погрузка завершена. Объём: {$volume} т, порода: {$currentRock?->name_rock}",
            ]);

        } catch (\Exception $e) {
            Log::error('Complete loading failed', ['error' => $e->getMessage()]);
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    // =========================================
    // СТАТИСТИКА
    // =========================================

    protected function getShiftStats(): array
    {
        if (!$this->miner) {
            return [];
        }

        $shiftStart = $this->getShiftStart();

        // Все рейсы за смену с completed_at (любой miner_id)
        $allCompletedTrips = TruckTrip::whereNotNull('completed_at')
            ->where('completed_at', '>=', $shiftStart)
            ->get(['id', 'miner_id', 'completed_at', 'load_volume']);

      
        $trips = TruckTrip::where('miner_id', $this->miner->id)
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', $shiftStart)
            ->with('truck')
            ->get();

        
        $tripsCount = $trips->count();
        $totalVolume = $trips->sum('load_volume');

        $avgLoadingTime = $trips->whereNotNull('started_at')->whereNotNull('completed_at')->avg(function ($trip) {
            return $trip->started_at->diffInSeconds($trip->completed_at) / 60;
        });

        return [
            'trips_count' => $tripsCount,
            'total_volume' => round($totalVolume, 1),
            'avg_loading_time' => round($avgLoadingTime ?? 0, 1),
            'shift_start' => $shiftStart->format('H:i'),
            'shift_name' => $this->getShiftName(),
        ];
    }

    protected function getShiftStart(): \Carbon\Carbon
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

    protected function getShiftName(): string
    {
        $hour = now()->hour;
        $minute = now()->minute;

        if (($hour >= 7 && $hour < 19) || ($hour === 19 && $minute < 30)) {
            return '1-я смена';
        } else {
            return '2-я смена';
        }
    }

    // =========================================
    // REAL-TIME EVENTS
    // =========================================

    #[On('truck-arrived')]
    public function onTruckArrived(array $data): void
    {
      
        $this->loadMinerData();

        $this->dispatch('notify', [
            'type' => 'info',
            'message' => $data['message'] ?? 'Новый самосвал в пути к забою',
        ]);
    }

    public function render()
    {
        return view('livewire.excavator-panel');
    }
}
