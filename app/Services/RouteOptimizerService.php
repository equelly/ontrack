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
 */
class RouteOptimizerService
{
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
        
        // 2. Получить все активные забои
        $activeMiners = Miner::where('active', true)->with('currentRock')->get();
        Log::info("Активных забоев: {$activeMiners->count()}");
        
        if ($activeMiners->isEmpty()) {
            return $result;
        }
        
        // 3. Получить все маршруты и рассчитать score
        $routes = $this->getAllRoutesWithScore($activeMiners);
        Log::info("Маршрутов с score: {$routes->count()}");
        
        // 4. Распределить по раундам
        $assignments = $this->assignByRounds($routes, $activeMiners);
        
        // 5. Активировать выбранные маршруты
        foreach ($assignments as $round => $roundAssignments) {
            $result['rounds'][$round] = count($roundAssignments);
            
            foreach ($roundAssignments as $assignment) {
                MiningOrder::where('miner_id', $assignment['miner_id'])
                    ->where('dump_id', $assignment['dump_id'])
                    ->update(['active' => true]);
                
                $result['activated'][] = [
                    'miner_id' => $assignment['miner_id'],
                    'dump_id' => $assignment['dump_id'],
                    'round' => $round,
                    'score' => $assignment['score'],
                ];
            }
        }
        
        // 6. Статистика
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
        
        // Проверяем доступность зон
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
        $activeMiners = Miner::where('active', true)->with('currentRock')->get();
        $minerIds = $activeMiners->pluck('id')->toArray();
        
        return MiningOrder::whereIn('miner_id', $minerIds)
            ->with(['miner.currentRock', 'dump.zones'])
            ->get()
            ->map(function($order) {
                $miner = $order->miner;
                $rock = $miner?->currentRock;
                
                $distance = $order->distance_km ?? MinerDumpDistance::where('miner_id', $order->miner_id)
                    ->where('dump_id', $order->dump_id)
                    ->value('distance_km');
                
                // Доступные зоны
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
     * Получить все маршруты с рассчитанным score
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
            
            $distance = MinerDumpDistance::where('miner_id', $order->miner_id)
                ->where('dump_id', $order->dump_id)
                ->value('distance_km');
            
            if (!$distance) {
                continue;
            }
            
            $availableZones = $this->getAvailableZonesForRock($order->dump_id, $rockId);
            
            if ($availableZones->isEmpty()) {
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
     * Получить доступные зоны для породы на отвал
     */
    protected function getAvailableZonesForRock(int $dumpId, int $rockId): Collection
    {
        return Zone::where('dump_id', $dumpId)
            ->where('delivery', true)
            ->whereHas('rocks', fn($q) => $q->where('rocks.id', $rockId))
            ->whereRaw('volume < capacity')
            ->get();
    }
    
    /**
     * Распределить маршруты по раундам (балансировка)
     */
    protected function assignByRounds(Collection $routes, Collection $activeMiners): array
    {
        $assignments = [];
        $assignedMiners = [];
        
        $byMiner = $routes->groupBy('miner_id');
        
        $roundAssignments = [];
        
        foreach ($byMiner as $minerId => $minerRoutes) {
            $bestRoute = $minerRoutes->first();
            
            if ($bestRoute) {
                $roundAssignments[] = $bestRoute;
                $assignedMiners[] = $minerId;
            }
        }
        
        if (!empty($roundAssignments)) {
            $assignments[1] = $roundAssignments;
        }
        
        return $assignments;
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
                $distance = MinerDumpDistance::where('miner_id', $order->miner_id)
                    ->where('dump_id', $order->dump_id)
                    ->value('distance_km');
                
                $rock = $order->miner?->currentRock;
                
                $zones = $rock ? Zone::where('dump_id', $order->dump_id)
                    ->where('delivery', true)
                    ->whereHas('rocks', fn($q) => $q->where('rocks.id', $rock->id))
                    ->whereRaw('volume < capacity')
                    ->get() : collect();
                
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
