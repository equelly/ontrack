<?php

namespace App\Livewire;

use App\Models\Miner;
use App\Models\Rock;
use App\Models\Truck;
use App\Models\TruckTrip;
use App\Events\LoadingCompleted;
use App\Events\MinerProductivityUpdated;
use App\Services\TruckStatusService;
use App\Services\MinerStatusService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;

#[Layout('components.layouts.app')] 
#[Title('Панель экскаватора')] 

class ExcavatorPanel extends Component
{
    public ?Miner $miner = null;
    public $miners;
    public $rocks;
    public $trucks;
    public array $stats = [];
    public array $productivityStats = [];

    // Выбор экскаватора
    public ?int $selectedMinerId = null;

    // Выбор породы
    public ?int $selectedRockId = null;

    // Объёмы для грузовиков
    public array $volumes = [];

    // Целевое время погрузки (в минутах)
    public ?int $targetLoadTime = null;

    protected function rules(): array
    {
        return [
            'selectedMinerId' => 'nullable|exists:miners,id',
            'selectedRockId' => 'nullable|exists:rocks,id',
        ];
    }

    public function mount()
    {
        $this->miners = Miner::orderBy('name_miner')->get();
        // Все породы - экскаваторщик выбирает любую
        $this->rocks = Rock::orderBy('name_rock')->get();

        // Инициализируем значения по умолчанию
        $this->trucks = collect();
        $this->stats = [];
        $this->productivityStats = [];

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
        // Сбрасываем данные по умолчанию
        $this->miner = null;
        $this->trucks = collect();
        $this->stats = [];
        $this->productivityStats = [];
        $this->targetLoadTime = null;

        if (!$this->selectedMinerId) {
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

        // Загружаем целевое время погрузки
        $this->targetLoadTime = $this->miner->target_load_time;

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

        // Статистика производительности
        $this->productivityStats = $this->getProductivityStats();
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

        if (!$miner) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Экскаватор не найден',
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
        Log::info('setRock START', [
            'selectedRockId' => $this->selectedRockId,
            'miner_id' => $this->miner?->id,
            'old_current_rock_id' => $this->miner?->current_rock_id,
        ]);

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

        // ВАЖНО: обновляем модель в памяти
        $this->miner->refresh();

        Log::info('setRock AFTER UPDATE', [
            'miner_current_rock_id' => $this->miner->current_rock_id,
            'selectedRockId' => $this->selectedRockId,
        ]);

        // Добавляем породу в список добытых пород забоя (для статистики)
        $this->miner->rocks()->syncWithoutDetaching([$this->selectedRockId]);

        $rock = Rock::find($this->selectedRockId);

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Текущая порода: ' . ($rock?->name_rock ?? ''),
        ]);
    }

    /**
     * Установить статус забоя (задержка/в работе)
     */
    public function setStatus(string $status): void
    {
        Log::info('setStatus START', [
            'miner_id' => $this->miner?->id,
            'new_status' => $status,
        ]);

        if (!$this->miner) {
            $this->dispatch('notify', [
                'type' => 'warning',
                'message' => 'Сначала выберите экскаватор',
            ]);
            return;
        }

        try {
            $statusService = app(MinerStatusService::class);
            $result = $statusService->changeStatus($this->miner, $status, Auth::id());

            if ($result['success']) {
                $this->miner->refresh();
                $this->loadMinerData();

                $this->dispatch('notify', [
                    'type' => in_array($status, Miner::STATUSES_DELAYED) ? 'warning' : 'success',
                    'message' => $result['message'],
                ]);
            } else {
                $this->dispatch('notify', [
                    'type' => 'error',
                    'message' => $result['message'],
                ]);
            }
        } catch (\Exception $e) {
            Log::error('setStatus failed', ['error' => $e->getMessage()]);
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Ошибка: ' . $e->getMessage(),
            ]);
        }
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
                    'zone_delivery' => $zone->delivery,
                    'zone_volume' => $zone->volume,
                    'zone_capacity' => $zone->capacity,
                    'zone_is_available' => $zone->delivery && $zone->volume < $zone->capacity,
                ]);

                // Проверяем, есть ли эта порода в текущей зоне
                if (!in_array($actualRock->id, $zoneRockIds)) {
                    Log::info("Zone {$zone->id} doesn't have rock {$actualRock->id}, looking for new zone");

                    $routeService = app(\App\Services\RouteAssignmentService::class);
                    
                    // Ищем новую зону на текущей перегрузке
                    $newZone = $routeService->selectZoneForRock($trip->dump_id, $actualRock->id);
                    
                    Log::info('selectZoneForRock result (same dump)', [
                        'dump_id' => $trip->dump_id,
                        'rock_id' => $actualRock->id,
                        'found' => $newZone ? $newZone->id : null,
                        'zone_name' => $newZone?->name_zone,
                    ]);

                    // Если не нашли на текущей перегрузке - ищем на всех
                    if (!$newZone) {
                        Log::info('No zone on current dump, searching all dumps');
                        
                        $allZonesForRock = \App\Models\Zone::where('delivery', true)
                            ->whereHas('rocks', fn($q) => $q->where('rocks.id', $actualRock->id))
                            ->whereRaw('volume < capacity')
                            ->get(['id', 'name_zone', 'dump_id', 'delivery', 'volume', 'capacity']);
                        
                        Log::info('All available zones for this rock', [
                            'rock_id' => $actualRock->id,
                            'count' => $allZonesForRock->count(),
                            'zones' => $allZonesForRock->map(fn($z) => [
                                'id' => $z->id,
                                'name' => $z->name_zone,
                                'dump_id' => $z->dump_id,
                                'delivery' => $z->delivery,
                                'volume' => $z->volume,
                                'capacity' => $z->capacity,
                            ])->toArray(),
                        ]);
                        
                        $newZone = $allZonesForRock->first();
                        
                        if ($newZone) {
                            // Меняем перегрузку в trip и mining_order
                            $trip->update([
                                'dump_id' => $newZone->dump_id,
                                'zone_id' => $newZone->id,
                                'rock_id' => $actualRock->id,
                            ]);
                            if ($trip->miningOrder) {
                                $trip->miningOrder->update([
                                    'dump_id' => $newZone->dump_id,
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
                        // Нет зоны для этой породы - БЛОКИРУЕМ отправку!
                        Log::warning("No zone for rock {$actualRock->id} - BLOCKING truck departure");

                        // Сбрасываем зону в trip
                        $trip->update([
                            'load_volume' => $volume,
                            'loaded_at' => now(),
                            'rock_id' => $actualRock->id,
                            'zone_id' => null,
                        ]);

                        if ($trip->miningOrder) {
                            $trip->miningOrder->update(['zone_id' => null]);
                        }

                        $truck->update(['current_load' => $volume]);

                        // Переводим в статус ожидания
                        $statusService = app(TruckStatusService::class);
                        $statusService->changeStatus($truck, 'waiting_unloading', [
                            'reason' => 'no_zone_available',
                            'rock_id' => $actualRock->id,
                        ]);

                        // Уведомляем диспетчера
                        event(new \App\Events\DispatcherNotification(
                            $truck->id,
                            'waiting_for_zone_decision',
                            [
                                'trip_id' => $trip->id,
                                'rock_id' => $actualRock->id,
                                'rock_name' => $actualRock->name_rock,
                                'dump_id' => $trip->dump_id,
                                'miner_id' => $this->miner?->id,
                                'miner_name' => $this->miner?->name_miner,
                                'volume' => $volume,
                                'message' => "Требуется решение: нет зоны для породы {$actualRock->name_rock} (самосвал {$truck->number})",
                                'requires_action' => true,
                            ]
                        ));

                        $this->loadMinerData();

                        $this->dispatch('notify', [
                            'type' => 'warning',
                            'message' => "Погрузка завершена. Нет зоны для породы {$actualRock->name_rock}. Водитель ожидает решения диспетчера.",
                        ]);

                        Log::info("Truck {$truck->id} BLOCKED - waiting for dispatcher decision");
                        return; // ВАЖНО: выходим, не отправляем грузовик!
                    }
                }
            }
            
            // Меняем статус грузовика через сервис (отправит уведомление диспетчеру)
            $truck->update(['current_load' => $volume]);
            $statusService = app(TruckStatusService::class);
            $statusService->changeStatus($truck, 'transporting');


            // Получаем актуальную информацию
            $trip->refresh();
            $trip->miningOrder?->refresh();
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
    // ПРОИЗВОДИТЕЛЬНОСТЬ
    // =========================================

    /**
     * Установить целевое время погрузки
     */
    public function setTargetLoadTime(): void
    {
        if (!$this->miner) {
            $this->dispatch('notify', [
                'type' => 'warning',
                'message' => 'Сначала выберите экскаватор',
            ]);
            return;
        }

        if (!$this->targetLoadTime || $this->targetLoadTime < 1) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Укажите время погрузки (минимум 1 минута)',
            ]);
            return;
        }

        $this->miner->update([
            'target_load_time' => $this->targetLoadTime,
        ]);

        // Обновляем статистику
        $this->productivityStats = $this->getProductivityStats();

        // Отправляем real-time уведомление диспетчеру
        event(new MinerProductivityUpdated(
            $this->miner->id,
            $this->productivityStats
        ));

        Log::info('MinerProductivityUpdated event sent', [
            'miner_id' => $this->miner->id,
            'target_load_time' => $this->targetLoadTime,
        ]);

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => "Целевое время погрузки: {$this->targetLoadTime} мин",
        ]);
    }

    /**
     * Получить статистику производительности
     */
    protected function getProductivityStats(): array
    {
        if (!$this->miner) {
            return [];
        }

        // Получаем рекомендации из модели
        $recommendations = $this->miner->getRecommendedTruckCount();

        // Статистика по последним 5 рейсам
        $recentTrips = $this->miner->getRecentTrips(5);

        return [
            'target_load_time' => $this->miner->target_load_time,
            'avg_load_time' => $this->miner->getAvgLoadTime(5),
            'avg_wait_time' => $this->miner->getAvgWaitTime(5),
            'current_trucks' => $recommendations['current'] ?? 0,
            'waiting_trucks' => $recommendations['waiting'] ?? 0,
            'loading_trucks' => $recommendations['loading'] ?? 0,
            'recommended_trucks' => $recommendations['recommended'] ?? null,
            'balance' => $recommendations['balance'] ?? null,
            'avg_trip_time' => $recommendations['avg_trip_time'] ?? null,
            'recent_trips_count' => $recentTrips->count(),
        ];
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
        /**
     * Слушаем событие начала погрузки от водителя через Echo
     * TruckStartedLoading отправляется на private-miner.{minerId} с broadcastAs '.loading.started'
     */
    #[On('echo-private:miner.{miner.id},.loading.started')]
    public function onLoadingStarted(array $data): void
    {
        Log::info('LoadingStarted event received via Echo', $data);
        $this->loadMinerData();

        $this->dispatch('notify', [
            'type' => 'info',
            'message' => "Самосвал {$data['truck_number']} начал погрузку",
        ]);
    }


    public function render()
    {
        return view('livewire.excavator-panel');
    }
}
