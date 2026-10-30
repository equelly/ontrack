<?php

namespace App\Services;

use App\Models\Miner;
use App\Models\Dump;
use App\Models\Zone;
use App\Models\MiningOrder;
use App\Models\MinerDumpDistance;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

/**
 * RouteOptimizerService - оптимизация маршрутов
 * 
 * Режимы работы:
 * - auto: система автоматически выбирает лучшие маршруты
 * - manual: диспетчер вручную управляет активностью маршрутов
 *
 * Алгоритм выбора (подход C — гибрид):
 *   1. Деактивируем все маршруты (active=false)
 *   2. Сбрасываем wrr_cursor (новая смена/оптимизация)
 *   3. Для каждого активного забоя находим все подходящие маршруты (забой→отвал):
 *      - у забоя есть текущая порода
 *      - на отвалe есть доступная зона (delivery=true, volume<capacity, принимает породу
 *        с учётом fallback-логики: руда_ЦПТ → руда → руда_Sera)
 *      - есть расстояние (distance_km из MiningOrder ИЛИ из miner_dump_distances)
 *   4. Рассчитываем score = (distance × 10) × max(volume_in_zones/1000, 0.1)
 *      Меньше score = лучше (короткое расстояние + мало заполненные зоны)
 *   5. Распределяем по раундам (Round Robin):
 *      Раунд 1: каждому забою — его лучший маршрут
 *      Раунд 2: каждому забою — следующий лучший (резервный)
 *      ...
 *      Количество раундов = динамическое (минимум из: N доступных пар, 5 максимум)
 *      В каждом раунде один отвал обслуживает только один забой (балансировка)
 *   6. Активируем выбранные маршруты
 *
 * ВАЖНО: optimize() вызывается только при:
 *   - начале новой смены (через ShiftPlanningService)
 *   - добавлении/удалении забоя или отвала (через MasterPanel)
 *   - ручном запуске диспетчером (кнопка «Оптимизировать»)
 *   НЕ вызывается при каждом событии изменения зоны/породы — для этого есть syncAllOrders()
 */
class RouteOptimizerService
{
    /**
     * Максимальное количество раундов.
     *
     * Раньше было ограничение MAX_ROUNDS = 5 — но это было костылём,
     * чтобы предотвратить бесконечный цикл. Теперь алгоритм завершается,
     * когда:
     *   - все забои получили маршрут, ИЛИ
     *   - раунд пустой (ни один забой не нашёл свободную зону)
     *
     * Это защитное ограничение оставлено на случай если в данных будет
     * рассинхрон (например, зона delivery=true но volume > capacity —
     * такие граничные случаи могут привести к зависанию).
     */
    const MAX_ROUNDS = 100;

    /**
     * Главная функция - оптимизировать маршруты (только в автоматическом режиме)
     */
    public function optimize(): array
    {
        // Проверяем режим
        if (SystemSetting::isManualMode()) {
            Log::info('RouteOptimizerService: ручной режим, оптимизация пропущена');
            return [
                'error' => 'Ручной режим активации маршрутов. Используйте ручное управление.',
                'mode' => 'manual',
            ];
        }
        
        Log::info('=== RouteOptimizerService::optimize START ===');
        
        $result = [
            'rounds' => [],
            'activated' => [],
            'deactivated' => [],
            'stats' => [],
            'mode' => 'auto',
        ];
        
        // 1. Деактивировать все маршруты
        MiningOrder::query()->update(['active' => false]);
        Log::info('Все маршруты деактивированы');
        
        // 2. Сбросить WRR-курсоры (новая оптимизация = новая смена)
        MiningOrder::query()->update(['wrr_cursor' => 0, 'last_assigned_at' => null]);
        Log::info('WRR-курсоры сброшены');
        
        // 3. Получить все работающие забои
        // ВАЖНО: фильтруем только по status=active.
        // Раньше было ещё `where('active', true)` — но поле active (булево)
        // убрано как наследие. Теперь status — единственный источник истины.
        $activeMiners = Miner::where('status', Miner::STATUS_ACTIVE)
            ->with('currentRock')
            ->get();
        Log::info("Работающих забоев: {$activeMiners->count()}");
        
        if ($activeMiners->isEmpty()) {
            return $result;
        }
        
        // 4. Получить все маршруты с рассчитанным score
        $routes = $this->getAllRoutesWithScore($activeMiners);
        Log::info("Маршрутов с score: {$routes->count()}");
        
        if ($routes->isEmpty()) {
            return $result;
        }
        
        // 5. Распределить по раундам (динамическое количество, макс. 5)
        $assignments = $this->assignByRounds($routes, $activeMiners);
        
        // 6. Активировать выбранные маршруты
        foreach ($assignments as $round => $roundAssignments) {
            $result['rounds'][$round] = count($roundAssignments);
            
            foreach ($roundAssignments as $assignment) {
                // Активируем маршрут И фиксируем выбранную зону.
                // После этого MiningOrder «знает» свою зону — самосвалам
                // не нужно искать зону в момент назначения, они сразу
                // едут на нужную (RouteAssignmentService использует
                // $order->zone_id если он задан).
                //
                // Если назначенная зона позже станет недоступна
                // (заполнится/закроется) — RouteAssignmentService
                // автоматически найдёт другую через selectZoneForRock()
                // и обновит zone_id в этой MiningOrder.
                MiningOrder::where('miner_id', $assignment['miner_id'])
                    ->where('dump_id', $assignment['dump_id'])
                    ->update([
                        'active'  => true,
                        'zone_id' => $assignment['assigned_zone_id'],
                    ]);

                $result['activated'][] = [
                    'miner_id'   => $assignment['miner_id'],
                    'dump_id'    => $assignment['dump_id'],
                    'zone_id'    => $assignment['assigned_zone_id'],
                    'zone_name'  => $assignment['assigned_zone_name'] ?? null,
                    'round'      => $round,
                    'score'      => $assignment['score'],
                ];
            }
        }
        
        // 7. Статистика
        $result['stats'] = [
            'total_miners' => $activeMiners->count(),
            'total_routes' => MiningOrder::count(),
            'active_routes' => MiningOrder::where('active', true)->count(),
            'rounds_count' => count($assignments),
        ];
        
        Log::info('=== RouteOptimizerService::optimize END ===', $result['stats']);
        
        return $result;
    }
    
    /**
     * Получить текущий режим
     */
    public function getMode(): string
    {
        return SystemSetting::getRouteActivationMode();
    }
    
    /**
     * Переключить режим
     */
    public function setMode(string $mode): array
    {
        if (!in_array($mode, ['auto', 'manual'])) {
            return ['error' => 'Неверный режим. Используйте: auto или manual'];
        }
        
        SystemSetting::setRouteActivationMode($mode);
        Log::info("Режим активации маршрутов изменён на: {$mode}");
        
        return [
            'mode' => $mode,
            'message' => $mode === 'auto' 
                ? 'Автоматический режим включён. Запустите routes:optimize для оптимизации.'
                : 'Ручной режим включён. Управляйте маршрутами вручную.',
        ];
    }
    
    /**
     * Вручную активировать маршрут
     */
    public function activateRoute(int $minerId, int $dumpId): array
    {
        $order = MiningOrder::where('miner_id', $minerId)
            ->where('dump_id', $dumpId)
            ->first();
        
        if (!$order) {
            return ['error' => 'Маршрут не найден'];
        }
        
        // Проверяем доступность зон (с учётом fallback пород)
        $miner = Miner::with('currentRock')->find($minerId);
        if ($miner && $miner->currentRock) {
            $zones = $this->getAvailableZonesForRock($dumpId, $miner->currentRock->id);
            if ($zones->isEmpty()) {
                return ['error' => 'Нет доступных зон для этого маршрута'];
            }
        }
        
        $order->update(['active' => true]);
        Log::info("Маршрут активирован вручную: забой {$minerId} → отвал {$dumpId}");
        
        return [
            'success' => true,
            'miner_id' => $minerId,
            'dump_id' => $dumpId,
        ];
    }
    
    /**
     * Вручную деактивировать маршрут
     */
    public function deactivateRoute(int $minerId, int $dumpId): array
    {
        $updated = MiningOrder::where('miner_id', $minerId)
            ->where('dump_id', $dumpId)
            ->update(['active' => false]);
        
        if ($updated === 0) {
            return ['error' => 'Маршрут не найден'];
        }
        
        Log::info("Маршрут деактивирован вручную: забой {$minerId} → отвал {$dumpId}");
        
        return [
            'success' => true,
            'miner_id' => $minerId,
            'dump_id' => $dumpId,
        ];
    }
    
    /**
     * Изменить вес маршрута
     */
    public function setRouteWeight(int $minerId, int $dumpId, int $weight): array
    {
        $order = MiningOrder::where('miner_id', $minerId)
            ->where('dump_id', $dumpId)
            ->first();
        
        if (!$order) {
            return ['error' => 'Маршрут не найден'];
        }
        
        $order->update(['weight' => max(1, min(1000, $weight))]);
        Log::info("Вес маршрута изменён: забой {$minerId} → отвал {$dumpId}, вес {$weight}");
        
        return [
            'success' => true,
            'miner_id' => $minerId,
            'dump_id' => $dumpId,
            'weight' => $order->weight,
        ];
    }
    
    /**
     * Получить все маршруты с информацией для отображения
     */
    public function getAllRoutesWithInfo(): Collection
    {
        $activeMiners = Miner::where('status', Miner::STATUS_ACTIVE)->with('currentRock')->get();
        $minerIds = $activeMiners->pluck('id')->toArray();
        
        return MiningOrder::whereIn('miner_id', $minerIds)
            ->with(['miner.currentRock', 'dump.zones'])
            ->get()
            ->map(function($order) {
                $miner = $order->miner;
                $rock = $miner?->currentRock;
                
                // Сначала берём distance_km из самого MiningOrder, потом из miner_dump_distances
                $distance = $order->distance_km ?? MinerDumpDistance::where('miner_id', $order->miner_id)
                    ->where('dump_id', $order->dump_id)
                    ->value('distance_km');
                
                // Доступные зоны (с учётом fallback пород)
                $availableZones = $rock ? $this->getAvailableZonesForRock($order->dump_id, $rock->id) : collect();
                
                // Score
                $score = null;
                if ($availableZones->isNotEmpty() && $distance) {
                    $volumeInZones = $availableZones->sum('volume');
                    $score = ($distance * 10) * max($volumeInZones / 1000, 0.1);
                }
                
                return [
                    'id' => $order->id,
                    'miner_id' => $order->miner_id,
                    'miner_name' => $miner?->name_miner,
                    'dump_id' => $order->dump_id,
                    'dump_name' => $order->dump?->name_dump,
                    'rock_name' => $rock?->name_rock,
                    'active' => $order->active,
                    'weight' => $order->weight,
                    'wrr_cursor' => $order->wrr_cursor,
                    'distance' => $distance,
                    'score' => $score ? round($score, 2) : null,
                    'available_zones' => $availableZones->count(),
                    'zones_available' => $availableZones->isNotEmpty(),
                ];
            })
            ->sortBy('miner_id');
    }
    
    /**
     * Получить все маршруты с рассчитанным score.
     *
     * ВАЖНО: использует distance_km из самого MiningOrder (если задан),
     * иначе из таблицы miner_dump_distances. Раньше требовалось наличие записи
     * в miner_dump_distances — теперь нет.
     *
     * ВАЖНО: использует fallback-логику пород через getAvailableZonesForRock().
     */
    protected function getAllRoutesWithScore(Collection $activeMiners): Collection
    {
        $minerIds = $activeMiners->pluck('id')->toArray();
        
        $orders = MiningOrder::whereIn('miner_id', $minerIds)
            ->with(['dump.zones.rocks'])
            ->get();
        
        $routes = collect();
        
        foreach ($orders as $order) {
            $miner = $activeMiners->firstWhere('id', $order->miner_id);
            
            if (!$miner || !$miner->currentRock) {
                continue;
            }
            
            $rockId = $miner->currentRock->id;
            
            // Сначала distance_km из MiningOrder, потом из miner_dump_distances
            $distance = $order->distance_km;
            if (!$distance) {
                $distance = MinerDumpDistance::where('miner_id', $order->miner_id)
                    ->where('dump_id', $order->dump_id)
                    ->value('distance_km');
            }
            
            if (!$distance) {
                // Нет расстояния — пропускаем (нельзя рассчитать score)
                Log::debug("Маршрут {$order->id} пропущен: нет distance_km", [
                    'miner_id' => $order->miner_id,
                    'dump_id' => $order->dump_id,
                ]);
                continue;
            }
            
            // Доступные зоны с учётом fallback пород
            $availableZones = $this->getAvailableZonesForRock($order->dump_id, $rockId);
            
            if ($availableZones->isEmpty()) {
                $rockName = $miner->currentRock?->name_rock ?? "id={$rockId}";
                Log::debug("Маршрут {$order->id} пропущен: нет доступных зон для породы «{$rockName}» на отвалe {$order->dump_id}");
                continue;
            }
            
            $volumeInZones = $availableZones->sum('volume');
            $score = ($distance * 10) * max($volumeInZones / 1000, 0.1);
            
            $routes->push([
                'miner_id' => $order->miner_id,
                'miner_name' => $miner->name_miner,
                'dump_id' => $order->dump_id,
                'dump_name' => $order->dump->name_dump,
                'weight' => $order->weight ?? 100,
                'distance' => $distance,
                'volume_in_zones' => $volumeInZones,
                'score' => round($score, 2),
                'available_zones' => $availableZones,
            ]);
        }
        
        return $routes->sortBy('score')->values();
    }
    
    /**
     * Получить доступные зоны для породы на отвалe.
     *
     * ВАЖНО: использует fallback-логику пород через RouteAssignmentService::ROCK_FALLBACK_CHAIN.
     * Для "руда_ЦПТ" ищутся зоны, принимающие "руда" или "руда_Sera",
     * если нет зон именно под "руда_ЦПТ".
     *
     * ВАЖНО: ищет по НАЗВАНИЮ породы (name_rock), а не по ID —
     * устойчив к изменению ID при пересоздании таблицы rocks.
     *
     * Возвращает ВСЕ доступные зоны (не одну), чтобы оптимизатор мог рассчитать
     * суммарный volume_in_zones для score.
     */
    protected function getAvailableZonesForRock(int $dumpId, int $rockId): Collection
    {
        // Получаем название породы по ID
        $rock = \App\Models\Rock::find($rockId);
        if (!$rock) {
            Log::debug("getAvailableZonesForRock: порода id={$rockId} не найдена в таблице rocks");
            return collect();
        }

        // Используем RouteAssignmentService для получения fallback-цепочки НАЗВАНИЙ пород
        $routeService = app(\App\Services\RouteAssignmentService::class);
        $acceptableRockNames = $routeService::ROCK_FALLBACK_CHAIN[$rock->name_rock] ?? [$rock->name_rock];

        // Возвращаем ВСЕ зоны на этом отвале, которые:
        // - delivery = true (открыты)
        // - volume < capacity (есть место)
        // - принимают ХОТЯ БЫ ОДНУ из acceptable пород (с учётом fallback)
        $zones = Zone::where('dump_id', $dumpId)
            ->where('delivery', true)
            ->whereRaw('volume < capacity')
            ->whereHas('rocks', function ($q) use ($acceptableRockNames) {
                $q->whereIn('rocks.name_rock', $acceptableRockNames);
            })
            ->orderBy('volume', 'asc')
            ->get();

        if ($zones->isEmpty()) {
            Log::debug("getAvailableZonesForRock: нет зон", [
                'dump_id' => $dumpId,
                'rock_name' => $rock->name_rock,
                'checked_rock_names' => $acceptableRockNames,
            ]);
        }

        return $zones;
    }
    
    /**
     * Распределить маршруты по раундам — циклический перебор зон.
     *
     * Алгоритм (по логике пользователя):
     *   1. Раунд 1: перебираем забои → для каждого ищем ЛУЧШУЮ зону
     *      (по score = distance × volume — ближняя + мало заполненная).
     *      Выбранная зона ИСКЛЮЧАЕТСЯ из перебора в этом раунде.
     *   2. Если остались забои без зоны (зон меньше чем забоев) →
     *      Раунд 2: зоны СНОВА доступны → повторяем перебор.
     *   3. Продолжаем пока все забои не получат зону
     *      или пока не останется свободных зон (volume >= capacity).
     *   4. Если забоев меньше чем зон → ближние к забоям зоны
     *      заполняются (score = distance × volume обеспечивает это).
     *
     * Score (distance × volume) НЕ сбрасывается между раундами —
     * это уровень контроля объёмов, так же как WRR — уровень контроля
     * между забоями.
     *
     * НЕТ FALLBACK — если зона доступна (volume < capacity), забой
     * получит её в следующем раунде. Если все зоны заполнены —
     * забой остаётся без маршрута (нечего делать).
     *
     * @param Collection $routes Маршруты с score (отсортированы по возрастанию score)
     * @param Collection $activeMiners Активные забои
     * @return array [round_number => [assignments]]
     */
    protected function assignByRounds(Collection $routes, Collection $activeMiners): array
    {
        $assignments = [];

        // Группируем маршруты по забою, сортируя внутри группы по score (возрастание)
        // Score = distance × (volume / 1000 + 0.1) — меньше = лучше
        $byMiner = $routes->groupBy('miner_id')->map(function ($group) {
            return $group->sortBy('score')->values();
        });

        // Забои, которые ещё не получили маршрут
        $unassignedMiners = $byMiner->keys()->toArray();

        Log::info('assignByRounds: старт (циклический перебор)', [
            'miners_count'  => count($unassignedMiners),
            'routes_count'  => $routes->count(),
        ]);

        $round = 0;

        while (!empty($unassignedMiners) && $round < self::MAX_ROUNDS) {
            $round++;
            $roundAssignments = [];
            // В каждом раунде зоны эксклюзивны (одна зона = один забой)
            // В следующем раунде зоны СНОВА доступны
            $usedZonesInThisRound = [];
            $stillUnassigned = [];

            foreach ($unassignedMiners as $minerId) {
                $minerRoutes = $byMiner[$minerId] ?? collect();
                if ($minerRoutes->isEmpty()) {
                    continue;
                }

                $assigned = false;

                // Ищем ЛУЧШУЮ доступную зону: перебираем маршруты забоя
                // (по возрастанию score = ближний + мало заполненный отвал),
                // а внутри каждого маршрута — все зоны этого отвала
                // (отсортированы по volume asc — менее заполненная впереди).
                //
                // РАНЬШЕ был баг: проверяли только ПЕРВУЮ зону отвала.
                // Если первая занята другим забоем в этом раунде — весь
                // отвал пропускался, хотя X2/X3 на нём свободны.
                // Теперь перебираем все зоны отвала.
                foreach ($minerRoutes as $route) {
                    $assignedZone = null;

                    foreach ($route['available_zones'] as $zone) {
                        if (!isset($usedZonesInThisRound[$zone->id])) {
                            $assignedZone = $zone;
                            break;
                        }
                    }

                    // Все зоны этого отвала заняты в текущем раунде
                    // → пробуем следующий маршрут (следующий отвал)
                    if (!$assignedZone) {
                        continue;
                    }

                    // Запоминаем выбранную зону в маршруте — оптимизатор
                    // запишет её в MiningOrder.zone_id при активации
                    $route['assigned_zone_id'] = $assignedZone->id;
                    $route['assigned_zone_name'] = $assignedZone->name_zone;
                    $route['assigned_zone_fill_pct'] = $assignedZone->capacity > 0
                        ? round(($assignedZone->volume / $assignedZone->capacity) * 100, 1)
                        : 0;

                    $roundAssignments[] = $route;
                    $usedZonesInThisRound[$assignedZone->id] = [
                        'miner_id'    => $minerId,
                        'dump_id'     => $route['dump_id'],
                        'zone_id'     => $assignedZone->id,
                        'zone_name'   => $assignedZone->name_zone,
                        'fill_pct'    => $route['assigned_zone_fill_pct'],
                    ];
                    $assigned = true;

                    // Удаляем использованный маршрут (на этот отвал)
                    // из доступных для следующих раундов.
                    // ВАЖНО: забой уже получил маршрут → он не попадёт
                    // в $unassignedMiners для следующих раундов. Эта
                    // чистка нужна только чтобы $byMiner не раздувался.
                    $byMiner[$minerId] = $minerRoutes->reject(function ($r) use ($route) {
                        return $r['dump_id'] === $route['dump_id'];
                    })->values();

                    break;
                }

                if (!$assigned) {
                    // Забой не нашёл свободную зону → остаётся для следующего раунда
                    $stillUnassigned[] = $minerId;
                }
            }

            if (!empty($roundAssignments)) {
                $assignments[$round] = $roundAssignments;
                Log::info("assignByRounds: раунд {$round} завершён", [
                    'assignments_count' => count($roundAssignments),
                    'used_zones'         => count($usedZonesInThisRound),
                    'still_unassigned'  => count($stillUnassigned),
                ]);
            }

            // Подготовка к следующему раунду
            $unassignedMiners = $stillUnassigned;

            // Если раунд пустой → заканчиваем (все зоны заняты или нет маршрутов)
            if (empty($roundAssignments)) {
                Log::info("assignByRounds: раунд {$round} пустой — завершение");
                break;
            }
        }

        if (!empty($unassignedMiners)) {
            // Детальная диагностика: для каждого забоя без маршрута
            // показываем, сколько у него было маршрутов и сколько зон.
            // Это помогает понять: маршрут отфильтрован в getAllRoutesWithScore
            // (нет available_zones) ИЛИ зоны были заняты другими в каждом раунде.
            $diagnostics = [];
            foreach ($unassignedMiners as $minerId) {
                $minerRoutes = $byMiner[$minerId] ?? collect();
                $miner = $activeMiners->firstWhere('id', $minerId);
                $rockName = $miner?->currentRock?->name_rock ?? 'НЕТ';

                $routesInfo = $minerRoutes->map(fn($r) => [
                    'dump_id'   => $r['dump_id'],
                    'dump_name' => $r['dump_name'] ?? '?',
                    'score'     => $r['score'],
                    'zones_cnt' => $r['available_zones']->count(),
                    'zones'     => $r['available_zones']->map(fn($z) => [
                        'id'        => $z->id,
                        'name'      => $z->name_zone,
                        'delivery'  => $z->delivery,
                        'fill_pct'  => $z->capacity > 0 ? round(($z->volume / $z->capacity) * 100, 1) : 0,
                    ])->toArray(),
                ])->toArray();

                $diagnostics[] = [
                    'miner_id'   => $minerId,
                    'miner_name' => $miner?->name_miner ?? '?',
                    'rock'       => $rockName,
                    'routes_left_after_5_rounds' => count($minerRoutes),
                    'routes'     => $routesInfo,
                ];
            }

            Log::warning("assignByRounds: не все забои получили маршрут — детальная диагностика", [
                'unassigned_count' => count($unassignedMiners),
                'diagnostics'      => $diagnostics,
            ]);
        }

        return $assignments;
    }

    /**
     * Рассчитать средний процент заполнения зон отвалa.
     * Используется для принятия решения об адаптивной балансировке.
     *
     * @param array $route Элемент из коллекции routes (с available_zones)
     * @return float Средний процент заполнения (0-100)
     */
    protected function calculateDumpFillPercent(array $route): float
    {
        $zones = $route['available_zones'] ?? collect();

        if ($zones->isEmpty()) {
            return 100.0; // Нет зон — считаем полностью заполненным (не делимся)
        }

        $totalFillPct = 0;
        $count = 0;

        foreach ($zones as $zone) {
            if ($zone->capacity > 0) {
                $totalFillPct += ($zone->volume / $zone->capacity) * 100;
                $count++;
            }
        }

        return $count > 0 ? round($totalFillPct / $count, 1) : 100.0;
    }
    
    /**
     * Деактивировать маршруты для забоя
     */
    public function deactivateMiner(int $minerId): int
    {
        return MiningOrder::where('miner_id', $minerId)->update(['active' => false]);
    }
    
    /**
     * Получить текущие активные маршруты с информацией
     */
    public function getActiveRoutesInfo(): Collection
    {
        return MiningOrder::where('active', true)
            ->with(['miner.currentRock', 'dump.zones'])
            ->get()
            ->map(function($order) {
                $distance = $order->distance_km ?? MinerDumpDistance::where('miner_id', $order->miner_id)
                    ->where('dump_id', $order->dump_id)
                    ->value('distance_km');
                
                $rock = $order->miner?->currentRock;
                
                $zones = $rock ? $this->getAvailableZonesForRock($order->dump_id, $rock->id) : collect();
                
                return [
                    'id' => $order->id,
                    'miner_id' => $order->miner_id,
                    'miner_name' => $order->miner?->name_miner,
                    'dump_id' => $order->dump_id,
                    'dump_name' => $order->dump?->name_dump,
                    'weight' => $order->weight,
                    'distance' => $distance,
                    'available_zones' => $zones->count(),
                    'rock' => $rock?->name_rock,
                ];
            });
    }
}