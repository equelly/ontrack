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
        // Все породы - экскаваторщик выбирает любую
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
                Log::info("Auto-updated user miner_id", [
                    'user_id' => $user->id,
                    'new_miner_id' => $minerId
                ]);
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

        $this->miner = Miner::with(['rocks', 'currentRock'])->find($this->selectedMinerId);

        if (!$this->miner) {
            return;
        }

        // Если есть текущая порода — устанавливаем в селект
        if ($this->miner->currentRock) {
            $this->selectedRockId = $this->miner->current_rock_id;
        }

        // Грузим грузовики
        $this->trucks = Truck::with(['trips' => function ($q) {
            $q->where('miner_id', $this->miner->id)
                ->whereNull('completed_at')
                ->with(['miningOrder.dump', 'miningOrder.zone', 'miningOrder.rock'])
                ->latest();
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

        // Получаем старую породу
        $oldRock = $this->miner->currentRock;

        // Записываем текущую породу
        $this->miner->update([
            'current_rock_id' => $this->selectedRockId,
            'last_updated_at' => now(),
            'last_updated_by' => Auth::id(),
        ]);

        // Добавляем породу в список добытых пород забоя (для статистики)
        $this->miner->rocks()->syncWithoutDetaching([$this->selectedRockId]);

        $this->loadMinerData();

        $rock = Rock::find($this->selectedRockId);
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Текущая порода: ' . ($rock?->name_rock ?? ''),
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

            // Получаем текущую породу из забоя (выбранную экскаваторщиком)
            $actualRock = $this->miner?->currentRock;

            Log::info('completeLoading START', [
                'truck_id' => $truck->id,
                'truck_number' => $truck->number,
                'trip_id' => $trip->id,
                'trip_dump_id' => $trip->dump_id,
                'trip_zone_id' => $trip->zone_id,
                'trip_mining_order_id' => $trip->mining_order_id,
                'miner_id' => $this->miner?->id,
                'miner_name' => $this->miner?->name_miner,
                'actual_rock_id' => $actualRock?->id,
                'actual_rock_name' => $actualRock?->name_rock,
                'mining_order_dump_id' => $trip->miningOrder?->dump_id,
                'mining_order_zone_id' => $trip->miningOrder?->zone_id,
                'mining_order_rock_id' => $trip->miningOrder?->rock_id,
            ]);

            // Обновляем рейс с породой
            $trip->update([
                'load_volume' => $volume,
                'loaded_at' => now(),
                'rock_id' => $actualRock?->id,
            ]);

            // Проверяем: соответствует ли зона загруженной породе?
            $zone = $trip->zone;
            $zoneReassigned = false;
            $newZoneName = $zone?->name_zone;
            $newDumpName = $trip->miningOrder?->dump?->name_dump;
            $oldZoneName = $zone?->name_zone;
            $oldDumpName = $trip->miningOrder?->dump?->name_dump;

            if ($actualRock && $zone) {
                // Получаем породы зоны
                $zoneRockIds = $zone->rocks()->pluck('rocks.id')->toArray();
                
                Log::info('Zone rock check', [
                    'zone_id' => $zone->id,
                    'zone_name' => $zone->name_zone,
                    'zone_rock_ids' => $zoneRockIds,
                    'actual_rock_id' => $actualRock->id,
                    'zone_has_rock' => in_array($actualRock->id, $zoneRockIds),
                ]);

                // Проверяем, есть ли эта порода в текущей зоне
                if (!in_array($actualRock->id, $zoneRockIds)) {
                    Log::info("Zone {$zone->id} doesn't have rock {$actualRock->id}, looking for new zone");

                    $routeService = app(\App\Services\RouteAssignmentService::class);
                    
                    // Ищем новую зону на текущей перегрузке
                    $newZone = $routeService->selectZoneForRock($trip->dump_id, $actualRock->id);

                    // Если не нашли на текущей перегрузке - ищем на всех
                    if (!$newZone) {
                        $newZone = \App\Models\Zone::where('delivery', true)
                            ->whereHas('rocks', fn($q) => $q->where('rocks.id', $actualRock->id))
                            ->whereRaw('volume < capacity')
                            ->orderBy('volume', 'asc')
                            ->first();
                        
                        if ($newZone) {
                            // Меняем перегрузку в trip и mining_order
                            $trip->update([
                                'dump_id' => $newZone->dump_id,
                                'zone_id' => $newZone->id,
                                'rock_id' => $actualRock->id,
                            ]);
                            if ($trip->miningOrder) {
                                $trip->miningOrder->update([
                                    'zone_id' => $newZone->id,
                                    'rock_id' => $actualRock->id,
                                ]);
                            }
                        }
                    } else {
                        // Меняем только зону
                        $trip->update(['zone_id' => $newZone->id]);
                        if ($trip->miningOrder) {
                            $trip->miningOrder->update([
                                'zone_id' => $newZone->id,
                                'rock_id' => $actualRock->id,
                            ]);
                        }
                    }

                    Log::info('New zone search result', [
                        'dump_id' => $trip->dump_id,
                        'rock_id' => $actualRock->id,
                        'new_zone_found' => $newZone ? $newZone->id : null,
                        'new_zone_name' => $newZone?->name_zone,
                        'new_dump_id' => $newZone?->dump_id,
                    ]);

                    if ($newZone) {
                        $zoneReassigned = true;
                        $newZoneName = $newZone->name_zone;
                        $newDumpName = $newZone->dump->name_dump;

                        Log::info("Zone reassigned for truck {$truck->id}", [
                            'old_zone' => $oldZoneName,
                            'old_dump' => $oldDumpName,
                            'new_zone' => $newZoneName,
                            'new_dump' => $newDumpName,
                            'rock' => $actualRock->name_rock,
                        ]);
                    } else {
                        // Нет зоны для этой породы - уведомляем диспетчера
                        event(new \App\Events\DispatcherNotification(
                            $truck->id,
                            'no_zone_for_rock',
                            [
                                'trip_id' => $trip->id,
                                'rock_id' => $actualRock->id,
                                'rock_name' => $actualRock->name_rock,
                                'dump_id' => $trip->dump_id,
                                'current_zone_id' => $zone->id,
                                'message' => "Нет зоны для породы {$actualRock->name_rock}",
                            ]
                        ));

                        Log::warning("No zone for rock {$actualRock->id} anywhere");
                    }
                }
            }

            // Меняем статус грузовика
            $statusService = app(TruckStatusService::class);
            $truck->current_load = $volume;
            $truck->save();
            $statusService->changeStatus($truck, 'transporting');

            // Получаем актуальную информацию
            $trip->refresh();
            $finalZone = $trip->zone;
            $finalDump = $trip->miningOrder?->dump ?? $trip->dump;

            // Отправляем уведомление водителю
            event(new LoadingCompleted(
                $truck,
                $zoneReassigned ? $finalZone?->name_zone : null,
                $zoneReassigned ? $finalDump?->name_dump : null
            ));

            Log::info("Loading completed for truck {$truck->id}", [
                'trip_id' => $trip->id,
                'volume' => $volume,
                'rock' => $actualRock?->name_rock,
                'zone' => $finalZone?->name_zone,
                'dump' => $finalDump?->name_dump,
                'zone_reassigned' => $zoneReassigned,
            ]);

            $this->loadMinerData();

            $message = "Погрузка завершена. Объём: {$volume} т, порода: {$actualRock?->name_rock}";
            if ($zoneReassigned) {
                $message .= ". Место разгрузки изменено: {$newDumpName} - {$newZoneName}";
            }

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => $message,
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

        // Логируем для отладки
        Log::debug('getShiftStats', [
            'miner_id' => $this->miner->id,
            'miner_name' => $this->miner->name_miner,
            'shift_start' => $shiftStart->toDateTimeString(),
        ]);

        // Все рейсы за смену с completed_at (любой miner_id)
        $allCompletedTrips = TruckTrip::whereNotNull('completed_at')
            ->where('completed_at', '>=', $shiftStart)
            ->get(['id', 'miner_id', 'completed_at', 'load_volume']);

        Log::debug('ALL completed trips this shift', [
            'count' => $allCompletedTrips->count(),
            'by_miner' => $allCompletedTrips->groupBy('miner_id')->map(fn($g) => $g->count())->toArray(),
        ]);

        $trips = TruckTrip::where('miner_id', $this->miner->id)
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', $shiftStart)
            ->with('truck')
            ->get();

        Log::debug('getShiftStats trips for this miner', [
            'count' => $trips->count(),
            'trips' => $trips->pluck('id', 'miner_id')->toArray(),
        ]);

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
        Log::info('TruckArrived event received', $data);
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
