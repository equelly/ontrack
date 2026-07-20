<?php

namespace App\Services;

use App\Models\Truck;
use App\Models\MiningOrder;
use App\Models\TruckTrip;
use App\Models\Zone;
use App\Models\Miner;
use App\Models\MinerDumpDistance;
use App\Events\DriverRouteUpdated;
use App\Events\DispatcherNotification;
use App\Events\ExcavatorNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


/**
 * RouteAssignmentService - назначение маршрутов грузовикам
 * 
 * Использует ТОЛЬКО активные маршруты (mining_orders.active = 1)
 * Score рассчитывается динамически
 */
class RouteAssignmentService
{
    const DEFAULT_LOADING_TIME_MINUTES = 5;
    const BUFFER_COEFFICIENT = 1.5;

    protected RouteOptimizerService $optimizer;

    public function __construct(RouteOptimizerService $optimizer)
    {
        $this->optimizer = $optimizer;
    }

    /**
     * Получить время погрузки для конкретного забоя
     * Приоритет: фактическое среднее > целевое > дефолт 5 минут
     */
    protected function getLoadingTimeForMiner(Miner $miner): float
    {
        // Сначала пробуем фактическое среднее время
        $avgLoadTime = $miner->getAvgLoadTime(5);
        if ($avgLoadTime && $avgLoadTime > 0) {
            return $avgLoadTime;
        }

        // Затем целевое время (установленное оператором) - в секундах, конвертируем в минуты
        if ($miner->target_load_time && $miner->target_load_time > 0) {
            return (float) $miner->target_load_time / 60;
        }

        // Дефолт
        return self::DEFAULT_LOADING_TIME_MINUTES;
    }

    /**
     * Назначить маршрут грузовику
     * 
     * Вызывается из TruckStatusService::onToMiner() после смены статуса на 'to_miner'
     * Также может вызываться для грузовиков в статусе 'free' или 'completed'
     * 
     * @throws \RuntimeException если нет доступных маршрутов
     */
    public function assignForTruck(Truck $truck): void
    {
        Log::info('assignForTruck START', ['truck_id' => $truck->id, 'status' => $truck->status]);

        // Разрешаем назначение для статусов: free, completed, to_miner
        if (!in_array($truck->status, ['free', 'completed', 'to_miner'])) {
            throw new \RuntimeException("Грузовик занят (статус: {$truck->status})");
        }

        DB::transaction(function () use ($truck) {
            // Получаем только АКТИВНЫЕ маршруты
            $activeOrders = MiningOrder::where('active', true)
                ->with(['miner.currentRock', 'dump.zones.rocks', 'zone'])
                ->get();

            Log::info('Активных маршрутов', ['count' => $activeOrders->count()]);

            if ($activeOrders->isEmpty()) {
                throw new \RuntimeException("Нет активных маршрутов");
            }

            // Фильтруем маршруты с доступными зонами
            $availableRoutes = $this->filterRoutesWithAvailableZones($activeOrders, $truck);

            Log::info('Доступных маршрутов', ['count' => count($availableRoutes)]);

            if (empty($availableRoutes)) {
                throw new \RuntimeException("Нет маршрутов с доступными зонами");
            }

            // Выбираем маршрут по WRR с учётом весов
            $selectedRoute = $this->selectByWeightedWRR($availableRoutes);

            Log::info('Выбран маршрут', [
                'order_id' => $selectedRoute['order']->id,
                'miner_id' => $selectedRoute['order']->miner_id,
                'dump_id' => $selectedRoute['order']->dump_id,
                'zone_id' => $selectedRoute['zone']->id,
                'weight' => $selectedRoute['order']->weight,
            ]);

            $this->createTripAndAssign(
                $truck,
                $selectedRoute['order'],
                $selectedRoute['zone'],
                $selectedRoute['rock_id']
            );

            $this->notifyDriver($truck, $selectedRoute['order'], 'route_assigned');
            $this->notifyDispatcher($truck, $selectedRoute['order'], 'route_assigned');
            $this->notifyExcavator($truck, $selectedRoute['order'], 'route_assigned');

            Log::info('assignForTruck END');
        });
    }

    /**
     * Проверяет, запрещена ли порода для грузовика
     */
    protected function isRockRestricted(int $truckId, int $rockId): bool
    {
        return \App\Models\TruckRestriction::where('truck_id', $truckId)
            ->where('rock_id', $rockId)
            ->exists();
    }

    /**
     * Фильтруем маршруты с доступными зонами
     */
    protected function filterRoutesWithAvailableZones($orders, Truck $truck): array
    {
        $available = [];

        foreach ($orders as $order) {
            $miner = $order->miner;

            // Проверяем активность и статус забоя
            if (!$miner || !$miner->active || !$miner->isWorking()) {
                continue;
            }

            $currentRock = $miner->currentRock;
            
            if (!$currentRock) {
                continue;
            }

            // Проверяем, можно ли назначить на этот забой
            if (!$this->canAssignToMiner($order)) {
                continue;
            }

            // Проверяем, не запрещена ли порода для грузовика
            if ($this->isRockRestricted($truck->id, $currentRock->id)) {
                Log::info("Пропускаем маршрут с породой {$currentRock->id} - запрещена для грузовика {$truck->id}");
                continue;
            }

            // Ищем доступную зону для текущей породы
            $zone = $this->selectZoneForRock($order->dump_id, $currentRock->id);

            if ($zone) {
                // Получаем время погрузки для этого забоя
                $loadingTime = $this->getLoadingTimeForMiner($miner);

                $available[] = [
                    'order' => $order,
                    'zone' => $zone,
                    'rock_id' => $currentRock->id,
                    'weight' => $order->weight ?? 100,
                    'loading_time' => $loadingTime,
                ];
            }
        }

        return $available;
    }

    /**
     * Выбор маршрута по Weighted WRR
     * Учитывает weight, wrr_cursor и last_assigned_at (чтобы не отправлять один за другим)
     * Теперь использует динамическое время погрузки для каждого забоя
     */
    protected function selectByWeightedWRR(array $routes): array
    {
        if (count($routes) === 0) {
            return null;
        }

        if (count($routes) === 1) {
            return $routes[0];
        }

        // Сортируем с учётом:
        // 1. last_assigned_at - если прошло меньше времени погрузки, повышаем score
        // 2. wrr_cursor / weight - базовый WRR
        // 3. Динамическое время погрузки для каждого забоя
        usort($routes, function($a, $b) {
            $baseScoreA = ($a['order']->wrr_cursor ?? 0) / max($a['weight'], 1);
            $baseScoreB = ($b['order']->wrr_cursor ?? 0) / max($b['weight'], 1);
            
            // Динамическое время погрузки в секундах для каждого маршрута
            $loadingTimeSecondsA = $a['loading_time'] * 60;
            $loadingTimeSecondsB = $b['loading_time'] * 60;
            
            // Штраф за недавнее назначение (чтобы не отправлять один за другим)
            $lastAssignedA = $a['order']->last_assigned_at;
            $lastAssignedB = $b['order']->last_assigned_at;
            
            $secondsSinceA = $lastAssignedA ? now()->diffInSeconds($lastAssignedA) : $loadingTimeSecondsA;
            $secondsSinceB = $lastAssignedB ? now()->diffInSeconds($lastAssignedB) : $loadingTimeSecondsB;
            
            // Если прошло меньше времени погрузки - добавляем штраф
            $penaltyA = ($secondsSinceA < $loadingTimeSecondsA) ? ($loadingTimeSecondsA - $secondsSinceA) * 10 : 0;
            $penaltyB = ($secondsSinceB < $loadingTimeSecondsB) ? ($loadingTimeSecondsB - $secondsSinceB) * 10 : 0;
            
            $scoreA = $baseScoreA + $penaltyA;
            $scoreB = $baseScoreB + $penaltyB;
            
            return $scoreA <=> $scoreB;
        });

        // Берём первый (наименее использованный с учётом веса и без штрафа)
        return $routes[0];
    }

    /**
     * Выбрать зону для конкретной породы
     */
    public function selectZoneForRock(int $dumpId, int $rockId): ?Zone
    {
        return Zone::where('dump_id', $dumpId)
            ->where('delivery', true)
            ->whereHas('rocks', function($q) use ($rockId) {
                $q->where('rocks.id', $rockId);
            })
            ->whereRaw('volume < capacity')
            ->orderBy('volume', 'asc') // Менее заполненные сначала
            ->first();
    }

    /**
     * Назначить маршруты всем свободным грузовикам (free или completed)
     */
    public function assignRoutesToAllFree(): int
    {
        // Ищем грузовики в статусе free (в отстое) или completed (ждут назначения)
        $freeTrucks = Truck::whereIn('status', ['free', 'completed'])->get();
        $count = 0;

        foreach ($freeTrucks as $truck) {
            try {
                $this->assignForTruck($truck);
                if ($truck->fresh()->status !== 'free') {
                    $count++;
                }
            } catch (\Exception $e) {
                Log::error("Ошибка назначения для грузовика {$truck->id}: " . $e->getMessage());
            }
        }

        return $count;
    }

    /**
     * Переназначить грузовик
     */
    public function reassignTruck(Truck $truck, MiningOrder $newOrder): bool
    {
        if (in_array($truck->status, ['loading', 'unloading'])) {
            Log::warning("Нельзя переназначить грузовик {$truck->id} в статусе {$truck->status}");
            return false;
        }

        return DB::transaction(function () use ($truck, $newOrder) {
            $this->cancelCurrentTrip($truck);

            if (!$newOrder->active) {
                Log::warning("Маршрут {$newOrder->id} не активен");
                return false;
            }

            $miner = $newOrder->miner;
            $currentRock = $miner?->currentRock;

            if (!$currentRock) {
                Log::warning("Нет породы в забое {$newOrder->miner_id}");
                return false;
            }

            $zone = $this->selectZoneForRock($newOrder->dump_id, $currentRock->id);
            
            if (!$zone) {
                Log::warning("Нет доступной зоны для маршрута {$newOrder->id}");
                return false;
            }

            $this->createTripAndAssign($truck, $newOrder, $zone, $currentRock->id);

            $this->notifyDriver($truck, $newOrder, 'route_reassigned');
            $this->notifyDispatcher($truck, $newOrder, 'route_reassigned');
            $this->notifyExcavator($truck, $newOrder, 'route_reassigned');

            return true;
        });
    }

    /**
     * Отменить текущее назначение
     */
    public function cancelCurrentTrip(Truck $truck): void
    {
        $trip = TruckTrip::where('truck_id', $truck->id)
            ->whereNull('completed_at')
            ->latest()
            ->first();

        if ($trip) {
            $trip->delete();
            Log::info("Trip {$trip->id} удалён для грузовика {$truck->id}");
        }
    }

    /**
     * Создать trip и назначить маршрут
     */
    protected function createTripAndAssign(Truck $truck, MiningOrder $order, Zone $zone, ?int $rockId = null): void
    {
        Log::info('createTripAndAssign START', [
            'truck_id' => $truck->id,
            'order_id' => $order->id,
            'zone_id' => $zone->id,
        ]);

        try {
            // Завершаем старые незавершённые trip
            TruckTrip::where('truck_id', $truck->id)
                ->whereNull('completed_at')
                ->update([
                    'completed_at' => now(),
                    'load_volume' => 0,
                ]);

            // Создаём новый trip
            $trip = TruckTrip::create([
                'truck_id' => $truck->id,
                'driver_id' => $truck->driver_id,
                'miner_id' => $order->miner_id,
                'dump_id' => $order->dump_id,
                'zone_id' => $zone->id,
                'rock_id' => $rockId,
                'mining_order_id' => $order->id,
                'started_at' => now(),
            ]);

            Log::info('TruckTrip created', ['trip_id' => $trip->id]);

            // Обновляем породу в miningOrder (чтобы водитель видел правильную породу)
            if ($rockId) {
                $order->update(['rock_id' => $rockId]);
            }

            // Обновляем wrr_cursor и last_assigned_at
            $newCursor = ($order->wrr_cursor ?? 0) + 1;
            $order->update([
                'wrr_cursor' => $newCursor,
                'last_assigned_at' => now(),
            ]);

            // Обновляем статус грузовика
            $truck->update(['status' => Truck::STATUS_TO_MINER]);

            Log::info("Маршрут назначен: грузовик {$truck->id} → забой {$order->miner_id} → зона {$zone->id}");

        } catch (\Exception $e) {
            Log::error('createTripAndAssign ERROR', ['message' => $e->getMessage()]);
        }
    }

    /**
     * Переназначить зону
     */
    public function reassignZone(Truck $truck, int $newZoneId): bool
    {
        if (!in_array($truck->status, ['transporting', 'waiting_unloading'])) {
            Log::warning("Нельзя переназначить зону в статусе {$truck->status}");
            return false;
        }

        $zone = Zone::findOrFail($newZoneId);

        if (!$zone->delivery || $zone->volume >= $zone->capacity) {
            Log::warning("Зона {$newZoneId} недоступна");
            return false;
        }

        return DB::transaction(function () use ($truck, $zone) {
            $trip = TruckTrip::where('truck_id', $truck->id)
                ->whereNull('completed_at')
                ->latest()
                ->first();

            if ($trip) {
                $trip->update(['zone_id' => $zone->id]);
                
                if ($trip->miningOrder) {
                    $trip->miningOrder->update(['zone_id' => $zone->id]);
                }

                Log::info("Грузовик {$truck->id} переназначен в зону {$zone->id}");
                
                $this->notifyDriver($truck, $trip->miningOrder, 'zone_reassigned');
                $this->notifyDispatcher($truck, $trip->miningOrder, 'zone_reassigned');
            }

            return true;
        });
    }

    /**
     * Переназначить грузовики при закрытии зоны
     */
    public function reassignOnZoneClose(Zone $zone): int
    {
        Log::info("reassignOnZoneClose START for zone {$zone->id}");

        $trucks = Truck::whereIn('status', ['to_miner', 'transporting'])
            ->whereHas('trips', function ($q) use ($zone) {
                $q->where('zone_id', $zone->id)
                  ->whereNull('completed_at');
            })
            ->with(['trips' => function ($q) use ($zone) {
                $q->where('zone_id', $zone->id)
                  ->whereNull('completed_at')
                  ->with('rock');
            }])
            ->get();

        $reassignedCount = 0;

        foreach ($trucks as $truck) {
            $trip = $truck->trips->first();
            if (!$trip || !$trip->rock_id) {
                continue;
            }

            $newZone = $this->selectZoneForRock($zone->dump_id, $trip->rock_id);

            if ($newZone && $newZone->id !== $zone->id) {
                $trip->update(['zone_id' => $newZone->id]);
                
                if ($trip->miningOrder) {
                    $trip->miningOrder->update(['zone_id' => $newZone->id]);
                }

                $this->notifyDriver($truck, $trip->miningOrder, 'zone_reassigned');
                
                Log::info("Грузовик {$truck->id} переназначен из зоны {$zone->id} в зону {$newZone->id}");
                $reassignedCount++;
            }
        }

        Log::info("reassignOnZoneClose END, reassigned: {$reassignedCount}");

        return $reassignedCount;
    }

    /**
     * Проверка: можно ли назначить на miner
     * Использует динамический расчёт на основе целевого/фактического времени погрузки
     */
    protected function canAssignToMiner(MiningOrder $order): bool
    {
        $miner = $order->miner;
        
        if (!$miner) {
            return false;
        }

        // Пробуем использовать рекомендации из модели Miner
        $recommendations = $miner->getRecommendedTruckCount();
        
        if ($recommendations) {
            // Используем рассчитанное оптимальное количество + буфер
            $maxCount = ($recommendations['recommended'] ?? 2) + 1; // +1 буфер
            $currentCount = $recommendations['current'] ?? $this->getCountOnMiner($miner->id);
            
            Log::debug('canAssignToMiner using recommendations', [
                'miner_id' => $miner->id,
                'recommended' => $recommendations['recommended'] ?? null,
                'current' => $currentCount,
                'max' => $maxCount,
            ]);
            
            return $currentCount < $maxCount;
        }

        // Fallback: старый метод расчёта если нет данных для рекомендаций
        $travelTime = MinerDumpDistance::where('miner_id', $order->miner_id)
            ->where('dump_id', $order->dump_id)
            ->value('travel_time_hours');

        if (!$travelTime) {
            // Если нет данных о расстоянии - разрешаем назначение
            return true;
        }

        $currentCount = $this->getCountOnMiner($order->miner_id);
        $maxCount = $this->getMaxCountForMiner($travelTime, $miner);

        return $currentCount < $maxCount;
    }

    /**
     * Количество грузовиков на miner (включая ожидающих)
     */
    protected function getCountOnMiner(int $minerId): int
    {
        return TruckTrip::where('miner_id', $minerId)
            ->whereNull('completed_at')
            ->whereIn('truck_id', function ($query) {
                $query->select('id')
                    ->from('trucks')
                    ->whereIn('status', ['to_miner', 'loading', 'waiting_loading']);
            })
            ->count();
    }

    /**
     * Максимальное количество грузовиков на miner
     * Теперь учитывает динамическое время погрузки
     */
    protected function getMaxCountForMiner(float $travelTimeHours, Miner $miner): int
    {
        $travelTimeMinutes = $travelTimeHours * 60;
        $loadingTime = $this->getLoadingTimeForMiner($miner);
        
        // Формула: T_рейса / T_погрузки * буфер
        $maxCount = ($travelTimeMinutes / $loadingTime) * self::BUFFER_COEFFICIENT;
        
        return (int) round($maxCount);
    }

    /**
     * Уведомление водителя
     */
    protected function notifyDriver(Truck $truck, MiningOrder $order, string $action = 'route_assigned'): void
    {
        if (!$truck->driver_id) {
            return;
        }

        event(new DriverRouteUpdated(
            (int) $truck->driver_id,
            [
                'truck_id' => $truck->id,
                'action' => $action,
                'order_id' => $order->id,
                'miner_id' => $order->miner_id,
                'dump_id' => $order->dump_id,
                'zone_id' => $order->zone_id,
            ]
        ));

        Log::info("DriverRouteUpdated ({$action}) sent for truck {$truck->id}");
    }

    /**
     * Уведомление диспетчера
     */
    protected function notifyDispatcher(Truck $truck, MiningOrder $order, string $action = 'route_assigned'): void
    {
        event(new DispatcherNotification(
            $truck->id,
            $action,
            [
                'order_id' => $order->id,
                'driver_id' => $truck->driver_id,
                'zone_id' => $order->zone_id,
            ]
        ));

        Log::info("DispatcherNotification ({$action}) sent for truck {$truck->id}");
    }

    /**
     * Уведомление экскаваторщика
     */
    protected function notifyExcavator(Truck $truck, MiningOrder $order, string $action = 'route_assigned'): void
    {
        if (!$order->miner_id) {
            return;
        }

        event(new ExcavatorNotification(
            $order->miner_id,
            $action,
            [
                'truck_id' => $truck->id,
                'truck_number' => $truck->number,
                'driver_name' => $truck->driver?->name,
                'status' => 'to_miner',
                'message' => $action === 'route_assigned'
                    ? "Самосвал {$truck->number} направляется к забою"
                    : "Самосвал {$truck->number} переназначен",
            ]
        ));

        Log::info("ExcavatorNotification ({$action}) sent for truck {$truck->id} to miner {$order->miner_id}");
    }
}
