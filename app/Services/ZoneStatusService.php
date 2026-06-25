<?php

namespace App\Services;

use App\Models\Zone;
use App\Models\Truck;
use App\Models\TruckTrip;
use App\Models\MiningOrder;
use App\Models\Miner;
use App\Models\TripPause;
use App\Models\SystemSetting;
use App\Events\DispatcherNotification;
use App\Events\DriverRouteUpdated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ZoneStatusService - управление нагрузкой зон разгрузки
 *
 * Цель: минимизация простоев оборудования
 * - Грузовики: минимизировать время ожидания погрузки/разгрузки
 * - Экскаваторы: минимизировать время ожидания грузовиков
 *
 * Грузовики универсальны - могут перевозить любую породу
 * При поиске альтернативы ищем МАРШРУТЫ (не зоны по породе)
 */
class ZoneStatusService
{
    // Пороговые значения по умолчанию (могут переопределяться через настройки)
    const DEFAULT_OVERLOAD_THRESHOLD = 3;
    const NORMALIZATION_THRESHOLD = 2; // Ниже этого значения = нормализация
    const WEIGHT_REDUCTION = 50; // Насколько уменьшать вес маршрута

    /**
     * Получить порог перегруженности зоны (настраиваемый)
     */
    protected function getOverloadThreshold(): int
    {
        return SystemSetting::getZoneOverloadThreshold();
    }

    /**
     * Получить нагрузку на зону
     */
    public function getZoneLoad(Zone $zone): array
    {
        // Грузовики, которые едут к зоне или на разгрузке
        $trucksToZone = Truck::whereIn('status', [
            Truck::STATUS_TRANSPORTING,
            Truck::STATUS_UNLOADING,
            Truck::STATUS_WAITING_UNLOADING,
        ])
            ->whereHas('trips', function ($q) use ($zone) {
                $q->where('zone_id', $zone->id)
                    ->whereNull('completed_at');
            })
            ->with(['trips' => function ($q) use ($zone) {
                $q->where('zone_id', $zone->id)
                    ->whereNull('completed_at');
            }])
            ->get();

        $waitingCount = $trucksToZone->where('status', Truck::STATUS_WAITING_UNLOADING)->count();
        $unloadingCount = $trucksToZone->where('status', Truck::STATUS_UNLOADING)->count();
        $transportingCount = $trucksToZone->where('status', Truck::STATUS_TRANSPORTING)->count();

        // Рассчитываем среднее время ожидания
        $avgWaitTime = $this->calculateAvgWaitTime($zone);

        // Заполняемость зоны
        $fillPercentage = $zone->capacity > 0
            ? round(($zone->volume / $zone->capacity) * 100, 1)
            : 0;

        return [
            'zone_id' => $zone->id,
            'zone_name' => $zone->name_zone,
            'dump_name' => $zone->dump?->name_dump,
            'total_trucks' => $trucksToZone->count(),
            'waiting_count' => $waitingCount,
            'unloading_count' => $unloadingCount,
            'transporting_count' => $transportingCount,
            'avg_wait_minutes' => $avgWaitTime,
            'fill_percentage' => $fillPercentage,
            'is_available' => $zone->isAvailable(),
            'is_overloaded' => $waitingCount >= $this->getOverloadThreshold(),
            'status' => $this->determineZoneStatus($waitingCount, $fillPercentage),
        ];
    }

    /**
     * Рассчитать среднее время ожидания разгрузки для зоны
     */
    protected function calculateAvgWaitTime(Zone $zone): ?float
    {
        // Берём последние завершённые поездки в эту зону
        $trips = TruckTrip::where('zone_id', $zone->id)
            ->whereNotNull('completed_at')
            ->orderBy('completed_at', 'desc')
            ->limit(10)
            ->with('pauses')
            ->get();

        if ($trips->isEmpty()) {
            return null;
        }

        $waitTimes = [];
        foreach ($trips as $trip) {
            $waitSeconds = $trip->pauses
                ->where('type', TripPause::TYPE_WAITING_UNLOADING)
                ->sum('duration_seconds');
            if ($waitSeconds > 0) {
                $waitTimes[] = $waitSeconds / 60;
            }
        }

        return !empty($waitTimes) ? round(array_sum($waitTimes) / count($waitTimes), 1) : null;
    }

    /**
     * Определить статус зоны
     */
    protected function determineZoneStatus(int $waitingCount, float $fillPercentage): string
    {
        if ($fillPercentage >= 95) {
            return 'full';
        }
        if ($waitingCount >= $this->getOverloadThreshold()) {
            return 'overloaded';
        }
        if ($waitingCount > 0) {
            return 'busy';
        }
        return 'available';
    }

    /**
     * Получить статистику по всем зонам
     */
    public function getAllZonesLoad(): array
    {
        $zones = Zone::with('dump', 'rocks')
            ->where('delivery', true)
            ->get();

        return $zones->map(fn($zone) => $this->getZoneLoad($zone))->toArray();
    }

    /**
     * ОБРАБОТКА СОБЫТИЯ: Водитель установил "ожидание разгрузки"
     * Автоматическая проверка и перенаправление
     */
    public function onWaitingUnloading(Truck $truck, Zone $zone): array
    {
        Log::info('=== ZoneStatusService::onWaitingUnloading ===', [
            'truck_id' => $truck->id,
            'truck_number' => $truck->number,
            'zone_id' => $zone->id,
            'zone_name' => $zone->name_zone,
        ]);

        $load = $this->getZoneLoad($zone);

        // Проверяем перегрузку
        if (!$load['is_overloaded']) {
            Log::info('Zone is not overloaded, no action needed', [
                'waiting_count' => $load['waiting_count'],
                'threshold' => $this->getOverloadThreshold(),
            ]);

            return [
                'action' => 'none',
                'reason' => 'Зона не перегружена',
                'load' => $load,
            ];
        }

        // Зона перегружена - запускаем автоматическую балансировку
        return $this->handleOverloadedZone($zone, $truck);
    }

    /**
     * Обработка перегруженной зоны
     */
    protected function handleOverloadedZone(Zone $zone, Truck $triggerTruck): array
    {
        Log::info('Zone is OVERLOADED, starting automatic balancing', [
            'zone_id' => $zone->id,
            'zone_name' => $zone->name_zone,
        ]);

        // Находим грузовики, которые МОЖНО перенаправить (только transporting)
        $trucksToRedirect = $this->findTransportingTrucksToZone($zone);

        // Находим породу, которую перевозят эти грузовики
        $rock = $this->getRockFromTrucks($trucksToRedirect);

        if (!$rock) {
            Log::warning('Cannot determine rock type for redirecting trucks');
            return [
                'action' => 'none',
                'reason' => 'Не удалось определить породу',
            ];
        }

        // Ищем альтернативные зоны с такой же породой
        $alternativeZones = $this->findAlternativeZonesForRock($rock, $zone);

        if ($alternativeZones->isEmpty()) {
            Log::warning('No alternative zones available for rock', [
                'rock_id' => $rock->id,
                'rock_name' => $rock->name_rock,
            ]);

            // Уменьшаем вес маршрутов на эту зону
            $this->reduceWeightForZone($zone);

            return [
                'action' => 'weight_reduced',
                'reason' => 'Нет альтернативных зон, уменьшен вес маршрутов',
                'rock' => $rock->name_rock,
            ];
        }

        // Перенаправляем грузовики
        $redirectedCount = 0;
        $redirectResults = [];

        foreach ($trucksToRedirect as $truck) {
            if ($redirectedCount >= $trucksToRedirect->count() - 2) {
                // Оставляем минимум 2 грузовика на разгрузку
                break;
            }

            $alternativeZone = $alternativeZones->first();

            if ($this->redirectTruckToZone($truck, $alternativeZone, $rock)) {
                $redirectedCount++;
                $redirectResults[] = [
                    'truck_id' => $truck->id,
                    'truck_number' => $truck->number,
                    'new_zone' => $alternativeZone->name_zone,
                ];
            }
        }

        // Уменьшаем вес маршрутов на перегруженную зону
        $this->reduceWeightForZone($zone);

        // Уведомляем диспетчера
        $this->notifyDispatcherAboutRedirect($zone, $redirectedCount, $alternativeZones->first(), $rock);

        Log::info('Automatic zone balancing completed', [
            'zone_id' => $zone->id,
            'redirected_count' => $redirectedCount,
            'alternative_zones' => $alternativeZones->pluck('name_zone')->toArray(),
        ]);

        return [
            'action' => 'redirected',
            'redirected_count' => $redirectedCount,
            'redirects' => $redirectResults,
            'alternative_zone' => $alternativeZones->first()?->name_zone,
            'rock' => $rock->name_rock,
        ];
    }

    /**
     * Найти грузовики в статусе transporting, едущие к зоне
     */
    protected function findTransportingTrucksToZone(Zone $zone)
    {
        return Truck::where('status', Truck::STATUS_TRANSPORTING)
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
    }

    /**
     * Определить породу из грузовиков
     */
    protected function getRockFromTrucks($trucks)
    {
        foreach ($trucks as $truck) {
            $trip = $truck->trips->first();
            if ($trip && $trip->rock) {
                return $trip->rock;
            }
        }
        return null;
    }

    /**
     * Найти альтернативные зоны для породы (исключая текущую перегруженную)
     */
    protected function findAlternativeZonesForRock($rock, Zone $excludeZone)
    {
        return Zone::where('id', '!=', $excludeZone->id)
            ->where('delivery', true)
            ->whereRaw('volume < capacity')
            ->whereHas('rocks', fn($q) => $q->where('rocks.id', $rock->id))
            ->withCount(['trips as waiting_count' => function ($q) {
                $q->whereNull('completed_at')
                    ->whereHas('truck', fn($q) => $q->where('status', Truck::STATUS_WAITING_UNLOADING));
            }])
            ->orderBy('waiting_count', 'asc') // Сначала менее загруженные
            ->orderBy('volume', 'asc')
            ->get();
    }

    /**
     * Перенаправить грузовик на другую зону
     */
    protected function redirectTruckToZone(Truck $truck, Zone $newZone, $rock): bool
    {
        $trip = $truck->trips->first();

        if (!$trip) {
            return false;
        }

        Log::info("Redirecting truck {$truck->number} to zone {$newZone->name_zone}");

        // Обновляем поездку
        $trip->update([
            'zone_id' => $newZone->id,
            'dump_id' => $newZone->dump_id,
        ]);

        // Обновляем mining_order если есть
        if ($trip->miningOrder) {
            $trip->miningOrder->update([
                'zone_id' => $newZone->id,
            ]);
        }

        // Уведомляем водителя
        event(new DriverRouteUpdated(
            (int) $truck->driver_id,
            [
                'truck_id' => $truck->id,
                'action' => 'zone_changed',
                'message' => "Зона разгрузки изменена на {$newZone->name_zone} (оригинальная зона перегружена)",
                'new_zone' => $newZone->name_zone,
                'new_dump' => $newZone->dump?->name_dump,
            ]
        ));

        return true;
    }

    /**
     * Уменьшить вес маршрутов, ведущих к перегруженной зоне
     */
    protected function reduceWeightForZone(Zone $zone): void
    {
        $orders = MiningOrder::where('zone_id', $zone->id)
            ->where('active', true)
            ->get();

        foreach ($orders as $order) {
            if (!$order->isWeightReduced()) {
                $order->reduceWeight(self::WEIGHT_REDUCTION);
            }
        }

        Log::info("Weight reduced for {$orders->count()} routes to zone {$zone->name_zone}");
    }

    /**
     * Восстановить вес маршрутов при нормализации зоны
     */
    public function checkAndRestoreWeight(Zone $zone): void
    {
        $load = $this->getZoneLoad($zone);

        if ($load['waiting_count'] < self::NORMALIZATION_THRESHOLD) {
            $orders = MiningOrder::where('zone_id', $zone->id)
                ->where('active', true)
                ->where('weight_adjustment', '<', 0)
                ->get();

            foreach ($orders as $order) {
                $order->restoreWeight();
            }

            if ($orders->count() > 0) {
                Log::info("Weight restored for {$orders->count()} routes to zone {$zone->name_zone}");
            }
        }
    }

    /**
     * Уведомить диспетчера о перенаправлении
     */
    protected function notifyDispatcherAboutRedirect(Zone $zone, int $count, ?Zone $newZone, $rock): void
    {
        event(new DispatcherNotification(
            0,
            'zone_overload',
            [
                'action' => 'auto_redirect',
                'zone_id' => $zone->id,
                'zone_name' => $zone->name_zone,
                'redirected_count' => $count,
                'new_zone' => $newZone?->name_zone,
                'rock' => $rock?->name_rock,
                'timestamp' => now()->toIso8601String(),
            ]
        ));
    }

    /**
     * Найти недозагруженный маршрут
     *
     * Ищем маршрут где:
     * - Забой активен
     * - Экскаватор недогружен (мало грузовиков, простаивает)
     * - Есть доступная зона для разгрузки (любая порода - грузовики универсальны)
     *
     * @param MiningOrder|null $excludeOrder Текущий маршрут (для исключения)
     * @return MiningOrder|null
     */
    public function findUnderloadedRoute(?MiningOrder $excludeOrder = null): ?MiningOrder
    {
        Log::info('=== ZoneStatusService::findUnderloadedRoute ===', [
            'exclude_order_id' => $excludeOrder?->id,
        ]);

        // Получаем все активные маршруты с активными забоями
        $query = MiningOrder::with(['miner', 'zone', 'dump', 'rock'])
            ->where('active', true)
            ->whereHas('miner', function ($q) {
                $q->where('status', Miner::STATUS_ACTIVE)
                    ->where('active', true);
            });

        if ($excludeOrder) {
            $query->where('id', '!=', $excludeOrder->id);
        }

        $routes = $query->get();

        if ($routes->isEmpty()) {
            Log::info('No active routes found');
            return null;
        }

        // Оцениваем каждый маршрут
        $routeScores = $routes->map(function ($route) {
            $miner = $route->miner;

            if (!$miner) {
                return null;
            }

            // Статистика забоя
            $minerStats = $miner->getRecommendedTruckCount();
            $recommendedTrucks = $minerStats['recommended'] ?? 2;
            $currentTrucks = $minerStats['current'] ?? 0;
            $minerCapacity = max(0, $recommendedTrucks - $currentTrucks);

            // Ищем доступную зону для этого маршрута
            // Грузовики универсальны - ищем ЛЮБУЮ доступную зону на перегрузке
            $availableZone = $this->findAvailableZoneForRoute($route);

            // Score = насколько маршрут недогружен
            // Чем выше score - тем более приоритетный для добавления грузовиков
            $score = 0;

            // Бонус за недогруженность экскаватора
            $score += $minerCapacity * 10;

            // Учитываем корректировку веса (если вес уменьшен - ниже приоритет)
            $score += $route->weight_adjustment ?? 0;

            // Если есть доступная зона - отличный маршрут
            if ($availableZone) {
                $score += 50;

                // Штраф если зона перегружена
                $zoneLoad = $this->getZoneLoad($availableZone);
                if ($zoneLoad['waiting_count'] > 0) {
                    $score -= $zoneLoad['waiting_count'] * 5;
                }

                // Штраф если зона почти полная
                if ($zoneLoad['fill_percentage'] > 80) {
                    $score -= 20;
                }
            } else {
                // Нет доступной зоны - плохой маршрут
                $score -= 100;
            }

            return [
                'route' => $route,
                'score' => $score,
                'miner_capacity' => $minerCapacity,
                'available_zone' => $availableZone,
                'miner_stats' => $minerStats,
            ];
        })->filter();

        // Сортируем по score (выше = лучше)
        $sorted = $routeScores->sortByDesc('score');

        // Берём лучший маршрут с доступной зоной
        foreach ($sorted as $item) {
            if ($item['miner_capacity'] > 0 && $item['available_zone']) {
                Log::info('Found underloaded route', [
                    'route_id' => $item['route']->id,
                    'score' => $item['score'],
                    'miner_name' => $item['route']->miner->name_miner,
                    'zone_name' => $item['available_zone']->name_zone ?? 'N/A',
                ]);
                return $item['route'];
            }
        }

        Log::info('No suitable underloaded route found');
        return null;
    }

    /**
     * Найти доступную зону для маршрута
     * Грузовики универсальны - ищем любую открытую зону с местом
     */
    public function findAvailableZoneForRoute(MiningOrder $route): ?Zone
    {
        // Сначала проверяем зону маршрута
        if ($route->zone && $route->zone->delivery && $route->zone->volume < $route->zone->capacity) {
            return $route->zone;
        }

        // Ищем любую доступную зону на перегрузке маршрута
        if ($route->dump) {
            $zone = Zone::where('dump_id', $route->dump_id)
                ->where('delivery', true)
                ->whereRaw('volume < capacity')
                ->orderBy('volume', 'asc')
                ->first();

            if ($zone) {
                return $zone;
            }
        }

        // Ищем любую доступную зону на любой перегрузке
        return Zone::where('delivery', true)
            ->whereRaw('volume < capacity')
            ->orderBy('volume', 'asc')
            ->first();
    }

    /**
     * Проверить, нужно ли перенаправить грузовики с перегруженной зоны
     */
    public function shouldRedirectFromZone(Zone $zone): array
    {
        $load = $this->getZoneLoad($zone);

        if (!$load['is_overloaded']) {
            return [
                'should_redirect' => false,
                'reason' => 'Зона не перегружена',
            ];
        }

        // Ищем альтернативный маршрут
        $currentOrders = MiningOrder::where('zone_id', $zone->id)
            ->where('active', true)
            ->get();

        $alternativeRoute = $this->findUnderloadedRoute($currentOrders->first());

        if (!$alternativeRoute) {
            return [
                'should_redirect' => false,
                'reason' => 'Нет доступных альтернативных маршрутов',
                'load' => $load,
            ];
        }

        return [
            'should_redirect' => true,
            'reason' => "Зона перегружена: {$load['waiting_count']} грузовиков в ожидании",
            'load' => $load,
            'alternative_route' => $alternativeRoute,
            'alternative_miner' => $alternativeRoute->miner,
            'alternative_zone' => $alternativeRoute->zone,
        ];
    }

    /**
     * Перенаправить грузовиков с перегруженной зоны на альтернативный маршрут
     * (для ручного вызова диспетчером)
     */
    public function redirectTrucksFromOverloadedZone(Zone $zone): int
    {
        $check = $this->shouldRedirectFromZone($zone);

        if (!$check['should_redirect']) {
            Log::info('Redirect not needed', ['reason' => $check['reason']]);
            return 0;
        }

        $alternativeRoute = $check['alternative_route'];
        $alternativeMiner = $alternativeRoute->miner;

        if (!$alternativeMiner) {
            Log::warning('Alternative route has no miner', ['route_id' => $alternativeRoute->id]);
            return 0;
        }

        // Находим доступную зону для альтернативного маршрута
        $alternativeZone = $this->findAvailableZoneForRoute($alternativeRoute);

        if (!$alternativeZone) {
            Log::warning('No available zone for alternative route', ['route_id' => $alternativeRoute->id]);
            return 0;
        }

        // Находим грузовиков, которые можно перенаправить
        // (только тех, кто ещё не начал разгрузку)
        $trucks = Truck::whereIn('status', [
            Truck::STATUS_TRANSPORTING,
            Truck::STATUS_WAITING_UNLOADING,
        ])
            ->whereHas('trips', function ($q) use ($zone) {
                $q->where('zone_id', $zone->id)
                    ->whereNull('completed_at');
            })
            ->with(['trips' => function ($q) use ($zone) {
                $q->where('zone_id', $zone->id)
                    ->whereNull('completed_at');
            }])
            ->get();

        // Перенаправляем только часть грузовиков
        // Оставляем минимум 1-2 на разгрузку
        $maxRedirect = max(0, $trucks->count() - 2);
        $redirectedCount = 0;

        foreach ($trucks as $truck) {
            if ($redirectedCount >= $maxRedirect) {
                break;
            }

            // Не перенаправляем если уже на разгрузке
            if ($truck->status === Truck::STATUS_UNLOADING) {
                continue;
            }

            $trip = $truck->trips->first();
            if (!$trip) {
                continue;
            }

            $this->reassignTruckToRoute($truck, $trip, $alternativeRoute, $alternativeZone);
            $redirectedCount++;
        }

        Log::info("Redirected {$redirectedCount} trucks from overloaded zone {$zone->id}");

        return $redirectedCount;
    }

    /**
     * Переназначить грузовик на другой маршрут
     */
    protected function reassignTruckToRoute(Truck $truck, TruckTrip $trip, MiningOrder $newRoute, ?Zone $newZone = null): void
    {
        $newMiner = $newRoute->miner;
        $newRock = $newRoute->rock ?? $newMiner?->currentRock;

        // Если зона не передана - ищем доступную
        if (!$newZone) {
            $newZone = $this->findAvailableZoneForRoute($newRoute);
        }

        Log::info("Reassigning truck {$truck->id} to route {$newRoute->id}", [
            'new_miner' => $newMiner?->name_miner,
            'new_zone' => $newZone?->name_zone,
        ]);

        // Если грузовик загружен - он везет старую породу
        // Нужно найти зону для этой породы
        if ($trip->load_volume > 0 && $trip->rock_id) {
            // Груз уже загружен - ищем зону для текущей породы
            $zoneForRock = Zone::where('delivery', true)
                ->whereRaw('volume < capacity')
                ->whereHas('rocks', fn($q) => $q->where('rocks.id', $trip->rock_id))
                ->orderBy('volume', 'asc')
                ->first();

            if ($zoneForRock) {
                $newZone = $zoneForRock;
            }
        }

        // Если всё ещё нет зоны - логируем ошибку и выходим
        if (!$newZone) {
            Log::error("Cannot reassign truck {$truck->id} - no available zone");
            return;
        }

        // Обновляем поездку
        $trip->update([
            'miner_id' => $newMiner?->id,
            'dump_id' => $newZone->dump_id,
            'zone_id' => $newZone->id,
            'rock_id' => $trip->load_volume > 0 ? $trip->rock_id : $newRock?->id,
            'mining_order_id' => $newRoute->id,
        ]);

        // Если грузовик не загружен - отправляем к новому забою
        if ($trip->load_volume <= 0) {
            $truck->update([
                'status' => Truck::STATUS_TO_MINER,
                'route_version' => $truck->route_version + 1,
            ]);

            event(new DriverRouteUpdated(
                (int) $truck->driver_id,
                [
                    'truck_id' => $truck->id,
                    'action' => 'route_reassigned',
                    'message' => "Зона разгрузки перегружена. Следуйте к забою {$newMiner?->name_miner}.",
                    'new_miner' => $newMiner?->name_miner,
                    'new_zone' => $newZone->name_zone,
                ]
            ));
        } else {
            // Грузовик загружен - отправляем на новую зону разгрузки
            $truck->update([
                'status' => Truck::STATUS_TRANSPORTING,
                'route_version' => $truck->route_version + 1,
            ]);

            event(new DriverRouteUpdated(
                (int) $truck->driver_id,
                [
                    'truck_id' => $truck->id,
                    'action' => 'zone_changed',
                    'message' => "Зона разгрузки изменена. Следуйте к зоне {$newZone->name_zone}.",
                    'new_zone' => $newZone->name_zone,
                ]
            ));
        }
    }

    /**
     * Проверить все зоны и вернуть список перегруженных
     */
    public function getOverloadedZones(): array
    {
        $zones = Zone::where('delivery', true)->get();
        $overloaded = [];

        foreach ($zones as $zone) {
            $check = $this->shouldRedirectFromZone($zone);
            if ($check['should_redirect']) {
                $overloaded[] = [
                    'zone' => $zone,
                    'load' => $check['load'],
                    'alternative_route' => $check['alternative_route'],
                ];
            }
        }

        return $overloaded;
    }

    /**
     * Автоматическая балансировка - перенаправить грузовики с перегруженных зон
     * (для ручного вызова или расписания)
     */
    public function autoBalance(): array
    {
        $overloaded = $this->getOverloadedZones();
        $results = [];

        foreach ($overloaded as $item) {
            $redirected = $this->redirectTrucksFromOverloadedZone($item['zone']);
            $results[] = [
                'zone_id' => $item['zone']->id,
                'zone_name' => $item['zone']->name_zone,
                'redirected_trucks' => $redirected,
            ];
        }

        // Уведомляем диспетчера о балансировке
        if (!empty($results)) {
            event(new DispatcherNotification(
                0,
                'zone_balancing',
                [
                    'action' => 'auto_balance',
                    'results' => $results,
                    'timestamp' => now()->toIso8601String(),
                ]
            ));
        }

        return $results;
    }
}