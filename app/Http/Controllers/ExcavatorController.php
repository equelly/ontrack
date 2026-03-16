<?php

namespace App\Http\Controllers;

use App\Models\Miner;
use App\Models\Rock;
use App\Models\Truck;
use App\Models\TruckTrip;
use App\Events\LoadingCompleted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ExcavatorController extends Controller
{
    /**
     * Главная панель машиниста
     */
    public function index(Request $request)
    {
        $minerId = $request->cookie('selected_miner');
        $miner = $minerId ? Miner::with('rocks')->find($minerId) : null;

        $miners = Miner::where('active', true)->orderBy('name_miner')->get();
        $rocks = Rock::orderBy('name_rock')->get();

        $trucks = collect();
        $stats = null;

        if ($miner) {
            $trucks = Truck::with(['trips' => function ($q) use ($miner) {
                $q->where('miner_id', $miner->id)
                ->whereNull('completed_at')
                ->with(['miningOrder.dump', 'miningOrder.zone']);
            }])
            ->whereIn('status', ['to_miner', 'loading', 'waiting_loading'])
            ->whereHas('trips', function ($q) use ($miner) {
                $q->where('miner_id', $miner->id)->whereNull('completed_at');
            })
            ->get();

            $stats = $this->getShiftStats($miner);
        }

        return view('excavator.show', compact('miner', 'miners', 'rocks', 'trucks', 'stats'));
    }

    /**
     * Установить выбранный экскаватор
     */
    public function setMiner(Request $request)
    {
        $request->validate([
            'miner_id' => 'required|exists:miners,id'
        ]);

        $miner = Miner::find($request->miner_id);

        if (!$miner || !$miner->active) {
            return response()->json(['success' => false, 'message' => 'Экскаватор не найден или неактивен']);
        }

        // Сохраняем привязку оператора к экскаватору
        Auth::user()->update(['miner_id' => $miner->id]);

        return response()->json([
            'success' => true,
            'miner' => $miner->load('rocks')
        ])->cookie('selected_miner', $miner->id, 60 * 24 * 30);
    }

    /**
     * Установить породу в забое
     */
    public function setRock(Request $request)
    {
        $request->validate([
            'miner_id' => 'required|exists:miners,id',
            'rock_id' => 'required|exists:rocks,id'
        ]);

        $miner = Miner::find($request->miner_id);

        // Получаем старую породу
        $oldRock = $miner->rocks->first();

        $miner->rocks()->sync([$request->rock_id]);
        $miner->update([
            'last_updated_at' => now(),
            'last_updated_by' => Auth::id()
        ]);

        // Если порода изменилась - обновляем rock_id в заказах (зону НЕ меняем)
        $updatedCount = 0;
        if (!$oldRock || $oldRock->id != $request->rock_id) {
            $routeService = app(\App\Services\RouteAssignmentService::class);
            $updatedCount = $routeService->updateRockForMinerChange(
                $miner->id,
                $request->rock_id
            );

            Log::info("Rock changed for miner {$miner->id}", [
                'old_rock' => $oldRock?->name_rock,
                'new_rock' => $miner->fresh()->rocks->first()?->name_rock,
                'updated_orders' => $updatedCount,
            ]);
        }

        return response()->json([
            'success' => true,
            'miner' => $miner->fresh()->load('rocks'),
            'updated_orders' => $updatedCount,
        ]);
    }

    /**
     * Подтвердить прибытие самосвала
     */
    public function confirmArrival(Request $request, Truck $truck)
    {
        if (!in_array($truck->status, ['to_miner', 'waiting_loading'])) {
            return response()->json([
                'success' => false,
                'message' => 'Самосвал не ожидает погрузки',
            ], 400);
        }

        $truck->update(['status' => 'loading']);

        Log::info("Truck {$truck->id} arrived at miner", ['status' => 'loading']);

        return response()->json([
            'success' => true,
            'message' => 'Самосвал прибыл на погрузку',
        ]);
    }

    /**
     * Завершить погрузку
     */
    public function completeLoading(Request $request, Truck $truck)
    {
        $request->validate([
            'volume' => 'nullable|numeric|min:0',
        ]);

        if ($truck->status !== 'loading') {
            return response()->json([
                'success' => false,
                'message' => 'Самосвал не на погрузке',
            ], 400);
        }

        $volume = $request->input('volume', $truck->load_capacity ?? 30);

        // Получаем активный рейс
        $trip = TruckTrip::where('truck_id', $truck->id)
            ->whereNull('completed_at')
            ->latest()
            ->first();

        if (!$trip) {
            return response()->json([
                'success' => false,
                'message' => 'Нет активного рейса',
            ], 400);
        }

        // Получаем текущую породу в забое (фактическая загруженная порода)
        $actualRock = $trip->miner?->rocks?->first();

        // Обновляем рейс - записываем ФАКТИЧЕСКУЮ породу
        $trip->update([
            'load_volume' => $volume,
            'loaded_at' => now(),
            'rock_id' => $actualRock?->id, // Фактическая порода из забоя
        ]);

        // Проверяем: соответствует ли зона загруженной породе?
        $zone = $trip->zone;
        $zoneReassigned = false;
        $newZoneName = $zone?->name_zone;

        if ($actualRock && $zone) {
            // Проверяем, есть ли эта порода в текущей зоне
            $zoneHasRock = $zone->rocks()->where('rocks.id', $actualRock->id)->exists();

            if (!$zoneHasRock) {
                Log::info("Zone {$zone->id} doesn't have rock {$actualRock->id}, looking for new zone");

                // Ищем новую зону для этой породы
                $routeService = app(\App\Services\RouteAssignmentService::class);
                $newZone = $routeService->selectZoneForRock($trip->dump_id, $actualRock->id);

                if ($newZone) {
                    // Обновляем зону
                    $trip->update(['zone_id' => $newZone->id]);
                    if ($trip->miningOrder) {
                        $trip->miningOrder->update([
                            'zone_id' => $newZone->id,
                            'rock_id' => $actualRock->id,
                        ]);
                    }

                    $zoneReassigned = true;
                    $newZoneName = $newZone->name_zone;

                    Log::info("Zone reassigned for truck {$truck->id}", [
                        'old_zone' => $zone->name_zone,
                        'new_zone' => $newZone->name_zone,
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

                    Log::warning("No zone for rock {$actualRock->id} in dump {$trip->dump_id}");
                }
            }
        }

        // Меняем статус грузовика
        $truck->update([
            'status' => 'transporting',
            'current_load' => $volume,
        ]);

        // Получаем актуальную информацию о зоне и дампе
        $trip->refresh();
        $finalZone = $trip->zone;
        $dump = $trip->miningOrder?->dump;

        // Отправляем уведомление водителю
        event(new LoadingCompleted(
            $truck,
            $finalZone?->name_zone,
            $dump?->name_dump
        ));

        Log::info("Loading completed for truck {$truck->id}", [
            'volume' => $volume,
            'zone' => $finalZone?->name_zone,
            'dump' => $dump?->name_dump,
            'actual_rock_id' => $actualRock?->id,
            'actual_rock_name' => $actualRock?->name_rock,
            'zone_reassigned' => $zoneReassigned,
        ]);

        $message = "Погрузка завершена. Объём: {$volume} т";
        if ($zoneReassigned) {
            $message .= ". Зона изменена на {$newZoneName}";
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'volume' => $volume,
            'rock_name' => $actualRock?->name_rock,
            'zone_reassigned' => $zoneReassigned,
            'new_zone' => $newZoneName,
        ]);
    }

    /**
     * Получить статистику за смену
     */
    protected function getShiftStats(Miner $miner): array
    {
        $shiftStart = $this->getShiftStart();

        $trips = TruckTrip::where('miner_id', $miner->id)
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
            'shift_name' => $this->getShiftName()
        ];
    }

    /**
     * Определить начало текущей смены
     */
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

    /**
     * Название текущей смены
     */
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
    
}