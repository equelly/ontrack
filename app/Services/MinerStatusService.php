<?php

namespace App\Services;

use App\Models\Miner;
use App\Models\MinerPause;
use App\Models\Truck;
use App\Models\TruckTrip;
use App\Models\MiningOrder;
use App\Models\Zone;
use App\Events\DispatcherNotification;
use App\Events\DriverRouteUpdated;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * MinerStatusService - управление статусами забоев
 *
 * При поломке: грузовики перенаправляются на другие забои
 * При плановой остановке: проверяется возможность перенаправления, если перегрузка - предупреждение
 */
class MinerStatusService
{
    protected RouteSyncService $routeSync;
    protected RouteAssignmentService $routeService;

    public function __construct(RouteSyncService $routeSync, RouteAssignmentService $routeService)
    {
        $this->routeSync = $routeSync;
        $this->routeService = $routeService;
    }

    /**
     * Проверить, можно ли безопасно остановить забой
     * Грузовики универсальны — могут работать с любой породой
     */
    public function canSafelyStop(Miner $miner): array
    {
        Log::info('canSafelyStop check', ['miner_id' => $miner->id, 'miner_name' => $miner->name_miner]);

        // 1. Найти ВСЕ активные забои (не важно какая порода)
        $alternativeMiners = Miner::where('id', '!=', $miner->id)
            ->where('status', Miner::STATUS_ACTIVE)
            ->where('active', true)
            ->get();

        if ($alternativeMiners->isEmpty()) {
            return [
                'safe' => false,
                'reason' => 'Нет других активных забоев',
                'warning' => 'Грузовики останутся без работы!',
                'total_capacity' => 0,
                'trucks_to_redirect' => $miner->getCurrentTrucksCount(),
                'alternatives' => [],
            ];
        }

        // 2. Фильтруем только те, у которых есть доступные зоны разгрузки
        $availableMiners = $alternativeMiners->filter(function ($alt) {
            $rock = $alt->currentRock;
            if (!$rock) {
                return false;
            }

            // Проверяем есть ли доступные зоны для этой породы
            return Zone::where('delivery', true)
                ->whereRaw('volume < capacity')
                ->whereHas('rocks', fn($q) => $q->where('rocks.id', $rock->id))
                ->exists();
        });

        if ($availableMiners->isEmpty()) {
            return [
                'safe' => false,
                'reason' => 'Нет забоев с доступными зонами разгрузки',
                'warning' => 'Некуда выгружать продукцию!',
                'total_capacity' => 0,
                'trucks_to_redirect' => $miner->getCurrentTrucksCount(),
                'alternatives' => [],
            ];
        }

        // 3. Рассчитываем вместимость каждого забоя
        $totalCapacity = 0;
        $minerDetails = [];

        foreach ($availableMiners as $alt) {
            $stats = $alt->getRecommendedTruckCount();
            $recommended = $stats['recommended'] ?? 2;
            $current = $stats['current'] ?? 0;
            $capacity = max(0, $recommended - $current);

            $minerDetails[] = [
                'id' => $alt->id,
                'name' => $alt->name_miner,
                'rock' => $alt->currentRock?->name_rock,
                'recommended' => $recommended,
                'current' => $current,
                'capacity' => $capacity,
                'balance' => $stats['balance'] ?? 'unknown',
            ];

            $totalCapacity += $capacity;
        }

        // Сортируем по вместимости (больше места — выше приоритет)
        usort($minerDetails, fn($a, $b) => $b['capacity'] <=> $a['capacity']);

        // 4. Сколько грузовиков нужно перенаправить
        $trucksToRedirect = $miner->getCurrentTrucksCount();

        // 5. Формируем результат
        $isSafe = $totalCapacity >= $trucksToRedirect;

        $result = [
            'safe' => $isSafe,
            'total_capacity' => $totalCapacity,
            'trucks_to_redirect' => $trucksToRedirect,
            'alternatives' => $minerDetails,
        ];

        if (!$isSafe) {
            $deficit = $trucksToRedirect - $totalCapacity;

            // Расчёт примерного времени ожидания
            $avgTripTime = 15; // минут (по умолчанию)
            foreach ($availableMiners as $alt) {
                $stats = $alt->getRecommendedTruckCount();
                if (!empty($stats['avg_trip_time'])) {
                    $avgTripTime = $stats['avg_trip_time'];
                    break;
                }
            }

            $waitMinutes = $totalCapacity > 0
                ? ceil($deficit * $avgTripTime / $totalCapacity)
                : $avgTripTime * $deficit;

            $result['reason'] = "Дефицит мест: {$deficit} грузовиков";
            $result['warning'] = "Перегрузка альтернативных забоев! Рекомендуется отложить на {$waitMinutes} мин.";
            $result['suggested_delay_minutes'] = $waitMinutes;
        } else {
            $result['reason'] = 'Альтернативные забои могут принять грузовики';
        }

        Log::info('canSafelyStop result', $result);

        return $result;
    }

    /**
     * Найти лучший альтернативный забой для перенаправления
     */
    public function findAlternativeMiner(?Miner $excludeMiner = null): ?Miner
    {
        $query = Miner::where('status', Miner::STATUS_ACTIVE)
            ->where('active', true);

        if ($excludeMiner) {
            $query->where('id', '!=', $excludeMiner->id);
        }

        $miners = $query->get();

        if ($miners->isEmpty()) {
            return null;
        }

        // Фильтруем забои с доступными зонами и сортируем по доступному месту
        $bestMiner = null;
        $bestCapacity = -1;

        foreach ($miners as $miner) {
            $rock = $miner->currentRock;
            if (!$rock) {
                continue;
            }

            // Проверяем доступные зоны
            $hasAvailableZone = Zone::where('delivery', true)
                ->whereRaw('volume < capacity')
                ->whereHas('rocks', fn($q) => $q->where('rocks.id', $rock->id))
                ->exists();

            if (!$hasAvailableZone) {
                continue;
            }

            // Рассчитываем доступную вместимость
            $stats = $miner->getRecommendedTruckCount();
            $capacity = ($stats['recommended'] ?? 2) - ($stats['current'] ?? 0);

            if ($capacity > $bestCapacity) {
                $bestCapacity = $capacity;
                $bestMiner = $miner;
            }
        }

        return $bestMiner;
    }

    /**
     * Изменить статус забоя (без проверки)
     */
    public function changeStatus(Miner $miner, string $newStatus, ?int $changedBy = null): array
    {
        return $this->doChangeStatus($miner, $newStatus, $changedBy);
    }

    /**
     * Изменить статус забоя с проверкой перегрузки (для плановых остановок)
     */
    public function changeStatusWithCheck(Miner $miner, string $newStatus, ?int $changedBy = null, bool $force = false): array
    {
        $oldStatus = $miner->status;

        Log::info('=== MinerStatusService::changeStatusWithCheck START ===', [
            'miner_id' => $miner->id,
            'miner_name' => $miner->name_miner,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'force' => $force,
        ]);

        // Проверяем допустимость перехода
        if (!$this->isValidTransition($oldStatus, $newStatus)) {
            return [
                'success' => false,
                'message' => "Недопустимый переход статуса: {$oldStatus} -> {$newStatus}",
            ];
        }

        // Для плановых остановок — проверяем возможность
        if (in_array($newStatus, Miner::STATUSES_PLANNED_DELAY) && !$force) {
            $safetyCheck = $this->canSafelyStop($miner);

            if (!$safetyCheck['safe']) {
                return [
                    'success' => false,
                    'requires_confirmation' => true,
                    'message' => $safetyCheck['warning'] ?? $safetyCheck['reason'],
                    'safety_check' => $safetyCheck,
                ];
            }
        }

        return $this->doChangeStatus($miner, $newStatus, $changedBy);
    }

    /**
     * Внутренний метод изменения статуса
     */
    protected function doChangeStatus(Miner $miner, string $newStatus, ?int $changedBy = null): array
    {
        $oldStatus = $miner->status;

        DB::transaction(function () use ($miner, $newStatus, $oldStatus, $changedBy) {
            // Управляем записями пауз
            if ($oldStatus === Miner::STATUS_ACTIVE && in_array($newStatus, Miner::STATUSES_DELAYED)) {
                $this->startPause($miner, $newStatus, $changedBy);
            } elseif (in_array($oldStatus, Miner::STATUSES_DELAYED) && $newStatus === Miner::STATUS_ACTIVE) {
                $this->endPause($miner, $changedBy);
            } elseif (in_array($oldStatus, Miner::STATUSES_DELAYED) && in_array($newStatus, Miner::STATUSES_DELAYED)) {
                $this->endPause($miner, $changedBy);
                $this->startPause($miner, $newStatus, $changedBy);
            }

            // Обновляем статус
            $miner->update([
                'status' => $newStatus,
                'status_changed_at' => now(),
                'status_changed_by' => $changedBy ?? Auth::id(),
            ]);

            // Обрабатываем грузовики ТОЛЬКО при поломке
            // При плановой остановке грузовики доезжают до забоя
            if ($newStatus === Miner::STATUS_BREAKDOWN) {
                $this->redirectTrucksOnBreakdown($miner);
            }

            // Деактивируем маршруты забоя при любой остановке
            if (in_array($newStatus, Miner::STATUSES_DELAYED)) {
                $this->deactivateRoutes($miner);

                // Запускаем полную оптимизацию маршрутов
                // Это активирует маршруты других забоев к тем же перегрузкам
                // и обеспечивает непрерывность поставок на точки разгрузки
                $optimizer = app(RouteOptimizerService::class);
                $optimizer->optimize();
            }

            // Активируем маршруты при возвращении в работу
            if ($newStatus === Miner::STATUS_ACTIVE) {
                $this->activateRoutes($miner);
            }
        });

        // Уведомляем диспетчера
        $this->notifyDispatcher($miner, $oldStatus, $newStatus);

        Log::info('=== MinerStatusService::changeStatus END ===', [
            'miner_id' => $miner->id,
            'new_status' => $newStatus,
        ]);

        return [
            'success' => true,
            'message' => "Статус изменён: {$miner->getStatusLabel()}",
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
        ];
    }

    /**
     * Начать паузу забоя
     */
    protected function startPause(Miner $miner, string $type, ?int $startedBy = null): void
    {
        MinerPause::create([
            'miner_id' => $miner->id,
            'type' => $type,
            'started_at' => now(),
            'started_by' => $startedBy ?? Auth::id(),
        ]);

        Log::info("Started pause for miner {$miner->id}", ['type' => $type]);
    }

    /**
     * Завершить активную паузу забоя
     */
    protected function endPause(Miner $miner, ?int $endedBy = null): void
    {
        $activePause = MinerPause::where('miner_id', $miner->id)
            ->whereNull('ended_at')
            ->latest()
            ->first();

        if ($activePause) {
            $activePause->update([
                'ended_at' => now(),
                'duration_seconds' => $activePause->started_at->diffInSeconds(now()),
                'ended_by' => $endedBy ?? Auth::id(),
            ]);

            Log::info("Ended pause for miner {$miner->id}", [
                'pause_id' => $activePause->id,
                'duration_seconds' => $activePause->duration_seconds,
            ]);
        }
    }

    /**
     * Проверить допустимость перехода статуса
     */
    protected function isValidTransition(string $from, string $to): bool
    {
        $validStatuses = array_keys(Miner::getAllStatuses());
        return in_array($to, $validStatuses);
    }

    /**
     * Перенаправить грузовики при поломке забоя
     */
    protected function redirectTrucksOnBreakdown(Miner $miner): int
    {
        Log::info("Redirecting trucks due to breakdown at miner {$miner->id}");

        // Находим альтернативный забой
        $alternativeMiner = $this->findAlternativeMiner($miner);

        // Грузовики в пути к забою или на погрузке
        $trucks = Truck::whereIn('status', ['to_miner', 'loading', 'waiting_loading'])
            ->whereHas('trips', function ($q) use ($miner) {
                $q->where('miner_id', $miner->id)
                    ->whereNull('completed_at');
            })
            ->with(['trips' => function ($q) use ($miner) {
                $q->where('miner_id', $miner->id)
                    ->whereNull('completed_at');
            }])
            ->get();

        $redirectedCount = 0;

        foreach ($trucks as $truck) {
            $trip = $truck->trips->first();

            if (!$trip) {
                continue;
            }

            // Если уже загружен - отправляем на разгрузку (НЕ трогаем!)
            if (in_array($truck->status, ['loading', 'waiting_unloading', 'transporting']) && $trip->load_volume > 0) {
                $this->sendLoadedTruckToUnloading($truck, $trip, $miner);
                $redirectedCount++;
                continue;
            }

            // Если на погрузке но не загружен
            if (in_array($truck->status, ['loading', 'waiting_loading'])) {
                if ($alternativeMiner) {
                    $this->reassignToAlternativeMiner($truck, $trip, $miner, $alternativeMiner);
                } else {
                    $this->releaseTruckFromLoading($truck, $trip, $miner);
                }
                $redirectedCount++;
                continue;
            }

            // Если едет к забою - перенаправляем на альтернативный
            if ($truck->status === 'to_miner') {
                if ($alternativeMiner) {
                    $this->reassignToAlternativeMiner($truck, $trip, $miner, $alternativeMiner);
                } else {
                    $this->releaseTruckFromTrip($truck, $trip, $miner);
                }
                $redirectedCount++;
            }
        }

        Log::info("Redirected {$redirectedCount} trucks from miner {$miner->id}");

        return $redirectedCount;
    }

    /**
     * Отправить загруженный грузовик на разгрузку (продолжает маршрут)
     */
    protected function sendLoadedTruckToUnloading(Truck $truck, TruckTrip $trip, Miner $miner): void
    {
        // Грузовик уже загружен - просто меняем статус на перевозку
        if ($truck->status === 'loading') {
            $truck->update(['status' => 'transporting']);
        }

        // Уведомляем водителя
        event(new DriverRouteUpdated(
            (int) $truck->driver_id,
            [
                'truck_id' => $truck->id,
                'action' => 'continue_to_unload',
                'message' => "Забой {$miner->name_miner} остановлен. Порода загружена — следуйте на разгрузку.",
            ]
        ));

        Log::info("Truck {$truck->id} continues to unloading from stopped miner {$miner->id}");
    }

    /**
     * Переназначить грузовик на альтернативный забой
     */
    protected function reassignToAlternativeMiner(Truck $truck, TruckTrip $trip, Miner $oldMiner, Miner $newMiner): void
    {
        Log::info("Reassigning truck {$truck->id} from miner {$oldMiner->id} to {$newMiner->id}");

        // Получаем породу нового забоя
        $newRock = $newMiner->currentRock;
        if (!$newRock) {
            $this->releaseTruckFromTrip($truck, $trip, $oldMiner);
            return;
        }

        // Ищем доступную зону для новой породы
        $newZone = $this->routeService->selectZoneForRock(
            $newMiner->orders()->first()?->dump_id ?? 1,
            $newRock->id
        );

        if (!$newZone) {
            // Ищем зону на всех отвалах
            $newZone = Zone::where('delivery', true)
                ->whereRaw('volume < capacity')
                ->whereHas('rocks', fn($q) => $q->where('rocks.id', $newRock->id))
                ->orderBy('volume', 'asc')
                ->first();
        }

        if (!$newZone) {
            Log::warning("No available zone for rock {$newRock->id}, releasing truck {$truck->id}");
            $this->releaseTruckFromTrip($truck, $trip, $oldMiner);
            return;
        }

        // Получаем или создаём маршрут для нового забоя
        $newOrder = MiningOrder::where('miner_id', $newMiner->id)
            ->where('dump_id', $newZone->dump_id)
            ->first();

        if (!$newOrder) {
            $newOrder = MiningOrder::create([
                'miner_id' => $newMiner->id,
                'dump_id' => $newZone->dump_id,
                'rock_id' => $newRock->id,
                'zone_id' => $newZone->id,
                'active' => true,
                'weight' => 100,
            ]);
        }

        // Обновляем текущий trip
        $trip->update([
            'miner_id' => $newMiner->id,
            'dump_id' => $newZone->dump_id,
            'zone_id' => $newZone->id,
            'rock_id' => $newRock->id,
            'mining_order_id' => $newOrder->id,
        ]);

        // Обновляем статус грузовика
        $truck->update([
            'status' => Truck::STATUS_TO_MINER,
            'route_version' => $truck->route_version + 1,
        ]);

        // Уведомляем водителя
        event(new DriverRouteUpdated(
            (int) $truck->driver_id,
            [
                'truck_id' => $truck->id,
                'action' => 'route_reassigned',
                'message' => "Маршрут изменён. Следуйте к забою {$newMiner->name_miner}.",
                'new_miner' => $newMiner->name_miner,
                'new_rock' => $newRock->name_rock,
            ]
        ));

        Log::info("Truck {$truck->id} reassigned to miner {$newMiner->id}, zone {$newZone->id}");
    }

    /**
     * Освободить грузовик с погрузки (не загружен)
     */
    protected function releaseTruckFromLoading(Truck $truck, TruckTrip $trip, Miner $miner): void
    {
        $trip->update([
            'completed_at' => now(),
            'load_volume' => 0,
        ]);

        $truck->update(['status' => 'completed']);

        event(new DriverRouteUpdated(
            (int) $truck->driver_id,
            [
                'truck_id' => $truck->id,
                'action' => 'loading_cancelled',
                'message' => "Погрузка отменена (остановка забоя {$miner->name_miner}). Ожидайте нового назначения.",
            ]
        ));

        Log::info("Truck {$truck->id} released from loading at stopped miner {$miner->id}");
    }

    /**
     * Освободить грузовик из поездки
     */
    protected function releaseTruckFromTrip(Truck $truck, TruckTrip $trip, Miner $miner): void
    {
        $trip->update([
            'completed_at' => now(),
            'load_volume' => 0,
        ]);

        $truck->update(['status' => 'completed']);

        event(new DriverRouteUpdated(
            (int) $truck->driver_id,
            [
                'truck_id' => $truck->id,
                'action' => 'route_cancelled',
                'message' => "Маршрут отменён (остановка забоя {$miner->name_miner}). Ожидайте нового назначения.",
            ]
        ));

        Log::info("Truck {$truck->id} released from trip to stopped miner {$miner->id}");
    }

    /**
     * Деактивировать маршруты забоя
     */
    protected function deactivateRoutes(Miner $miner): int
    {
        $count = MiningOrder::where('miner_id', $miner->id)
            ->where('active', true)
            ->update(['active' => false]);

        Log::info("Deactivated {$count} routes for miner {$miner->id}");

        return $count;
    }

    /**
     * Активировать маршруты забоя
     */
    protected function activateRoutes(Miner $miner): int
    {
        $count = MiningOrder::where('miner_id', $miner->id)
            ->update(['active' => true]);

        Log::info("Activated {$count} routes for miner {$miner->id}");

        // Запускаем оптимизацию для выбора лучших маршрутов
        $optimizer = app(RouteOptimizerService::class);
        $optimizer->optimize();

        return $count;
    }

    /**
     * Уведомить диспетчера о смене статуса
     */
    protected function notifyDispatcher(Miner $miner, string $oldStatus, string $newStatus): void
    {
        $event = in_array($newStatus, Miner::STATUSES_DELAYED) ? 'miner_delayed' : 'miner_resumed';

        event(new DispatcherNotification(
            0,
            $event,
            [
                'miner_id' => $miner->id,
                'miner_name' => $miner->name_miner,
                'old_status' => $oldStatus,
                'old_status_label' => match ($oldStatus) {
                    Miner::STATUS_ACTIVE => 'В работе',
                    Miner::STATUS_BREAKDOWN => 'Поломка',
                    Miner::STATUS_MAINTENANCE => 'Обслуживание',
                    Miner::STATUS_DISMANTLING => 'Разбор забоя',
                    Miner::STATUS_ACCESS_SETUP => 'Устройство подъезда',
                    default => $oldStatus,
                },
                'new_status' => $newStatus,
                'new_status_label' => $miner->getStatusLabel(),
                'duration_minutes' => $miner->getStatusDurationMinutes(),
                'requires_action' => $newStatus === Miner::STATUS_BREAKDOWN,
            ]
        ));

        Log::info("Dispatcher notified: {$event} for miner {$miner->id}");
    }
}
