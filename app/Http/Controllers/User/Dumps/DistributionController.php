<?php

namespace App\Http\Controllers\User\Dumps;

use App\Http\Controllers\Controller;
use App\Models\Miner;
use App\Models\Dump;
use App\Models\MinerDumpDistance;
use App\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DistributionController extends Controller
{

        public function index(Request $request)
    {
        // В начале установим режимы для сортировки
    // Чтение параметров и базовая статистика
    $mode = $request->input('mode', 'balance'); // volume, distance, balance

               // ✅ ВАЛИДАЦИЯ (только разрешённые режимы)
        $allowedModes = ['balance', 'volume', 'distance'];
        if (!in_array($mode, $allowedModes)) {
            $mode = 'balance'; // по умолчанию
        }

    // ✅ Получаем параметр из URL для фильтра активных зон
    $activeZonesOnly = $request->boolean('active_zones_only', false);

        // Загружаем расстояния между miners и dumps
    $distances = MinerDumpDistance::with(['miner'])
        ->whereHas('miner', function ($q) {
            $q->where('active', true);
        })
        ->get()
        ->groupBy('miner_id');

        // Добавляем в статистику
        $stats['total_miners_with_distances'] = $distances->keys()->count();
    
    // Инициализация переменных
    // ← СТАТИСТИКА МАРШРУТОВ (для среднего расстояния)
    $totalDistance = 0;  // ← Сумма расстояний ЛУЧШИХ маршрутов
    $totalAssignments = 0;  // ← Количество назначенных miner'ов
    $stats = [
        'total_assignments' => 0,
        'total_distance' => 0,
        'average_distance' => 0
    ];
    $distribution = [];
    $assignments = [];
    $totalDistance = 0;
    $totalTime = 0;
    $allOptions = [];
    $bestDistancies = 0;
    $stats['total_assignments'] = 0;
    // Базовый цикл: проходим по всем miners

    foreach ($distances as $minerId => $minerDistances) {

        $miner = $minerDistances->first()->miner;

        // Фильтруем только dumps с зонами 
    $suitableDumps = $minerDistances
        ->filter(function($record) use ($activeZonesOnly) {
            $dump = $record->dump;

            if ($activeZonesOnly) {
                // ✅ Только дампы с активными зонами
                return $dump->zones->where('delivery', true)->isNotEmpty();
            }

            // ✅ Все дампы с зонами
            return $dump->zones->isNotEmpty();
        })
        ->map(function($record) use ($activeZonesOnly) {
            $dump = $record->dump;

            // ✅ Выбираем зоны в зависимости от фильтра
            $zonesForCalc = $activeZonesOnly? $dump->zones->where('delivery', true): $dump->zones;

            $totalZoneVolume = $zonesForCalc->sum('volume');

            // Собираем названия пород для каждой зоны — по одной породе в зоне
            $zoneRocks = $zonesForCalc
            ->map(fn($zone) => $zone->rocks->first()->name_rock)
            ->unique()
            ->values()
            ->toArray();

                return [
                    'dump' => $dump,
                    'distance' => $record->distance_km,
                    'total_zone_volume' => $totalZoneVolume,
                    //емкость перегрузки (вместимость всех зон) принята условно 60 
                    //-конкретно можно для каждой зоны создать колонку capacity в табл. zones и затем ссумировать их как 'total_zone_volume'
                    'dump_volume' => $dump->capacity?? 60,
                    'rocks_names' => $zoneRocks, // здесь массив названий пород
                    'is_active_filter' => $activeZonesOnly,
                ];
            });


                //  ПОДГОТОВКА ЛОГИКИ РЕЖИМОВ
        $suitableDumpCount = $suitableDumps->count();
        $minerName = $miner->name_miner?? 'не установлен';
        // Проверяем режим и логируем


        if (!empty($suitableDumps)) {
            $dumpOptions = [];  

            // ✅ ОБЩИЕ РАСЧЁТЫ (для всех режимов)
            foreach ($suitableDumps as $index => $option) {
                $countSuitableDumps = (count($suitableDumps));
                $travelTimeHours = $option['distance'] / 20;
                $volume = $option['total_zone_volume'];
                $dumpCapacity = $option['dump']->capacity?? 60;
                $distance = $option['distance'];

                // ✅ SCORE ПО РЕЖИМУ СОРТИРОВКИ
                if ($mode === 'balance') {
                    // ТВОЯ ЛОГИКА БАЛАНСА (как у тебя)
                    $volumePercent = ($volume / $dumpCapacity) * 100;
                    $volumeScore = max(0, 100 - $volumePercent);
                    $distancePenalty = $distance * 10;
                    $distanceScore = max(0, 100 - $distancePenalty);
                    $score = round(($volumeScore * 0.3) + ($distanceScore * 0.7), 2);

                } elseif ($mode === 'volume') {
                    // ✅ ПРИОРИТЕТ МЕНЬШИМ ОБЪЁМАМ (маленькие зоны первыми!)
                    $inverseVolume = (1 / ($volume + 1)) * 1000; // 1/объём (маленький = большой score)
                    $distancePenalty = $distance * 3; // небольшой штраф за расстояние
                    $score = round($inverseVolume - $distancePenalty, 2);
                } else { // distance - ПРОСТО!
                    // Score обратно пропорционален расстоянию
                    $score = round((1 / ($distance + 0.1)) * 100, 2);
                    // 0.1км = 1000 баллов, 1км = 100, 10км = 10
                }


                $dumpOptions[] = [
                    'dump' => $option['dump'],
                    'distance' => $distance,
                    'total_zone_volume' => $volume,
                    'score' => $score,
                    'travel_time_hours' => round($travelTimeHours, 2),
                    'dump_volume' => $dumpCapacity,
                    'last_volume' => $dumpCapacity - $volume
                ];
                $stats['count'] = $countSuitableDumps;
            }

            // ✅ СОРТИРУЕМ (лучший первый)
            usort($dumpOptions, function($a, $b) {
                return $b['score'] <=> $a['score']; // По убыванию score
            });
           
            // ✅ БЕРЁМ ТОЛЬКО ПЕРВЫЙ (мы отсортировали массив и теперь он лучший!)
            if (!empty($dumpOptions)) {
                $bestOption = $dumpOptions[0];
                $allOptions[$minerId] = $dumpOptions;// сохраним этот массив с данными dump: расстояниями до него, score соответствующему режиму сортировки и т.д. 

               
                $distribution[$minerId] = [
                    'miner_name' => $miner->name_miner?? $minerId,
                    'dump_id' => $bestOption['dump']->id,
                    'name_dump' => $bestOption['dump']->name_dump,
                    //'total_available_zones' => $bestOption['total_available_zones'],
                    'total_zone_volume' => $bestOption['total_zone_volume'],
                    'distance_km' => $bestOption['distance'],
                    'travel_time_hours' => $bestOption['travel_time_hours'],
                    'dump_volume' => $bestOption['dump_volume'],
                    'last_volume' => $bestOption['last_volume'],
                    'score' => round($bestOption['score'], 2)
                ];
                
                $assignments[$minerId] = $distribution[$minerId];
                $bestDistancies += $bestOption['distance'];
                $totalTime += $bestOption['travel_time_hours'];
                $totalAssignments++;
                $stats['assignments'] = $assignments;
                $stats['total_assignments']++;
            }
        
        }

      

    }
    // ✅ Создаём структуру: dump_id => [варианты miners с score]
$allDumps = [];

// Идём по всем miner_id и их опциям
foreach ($allOptions as $minerId => $options) {
    foreach ($options as $option) {
        // Получаем ID дампа из объекта dump
        $dumpId = $option['dump']->id;

        // Создаём массив для этого дампа если не существует
        if (!isset($allDumps[$dumpId])) {
            $allDumps[$dumpId] = [];
        }

        // Добавляем вариант для этого дампа
        $allDumps[$dumpId][] = [
            'miner_id' => $minerId,
            'score' => $option['score'],
            'distance' => $option['distance'],
            'travel_time' => $option['travel_time_hours'],
            'volume' => $option['total_zone_volume'],
            'dump_volume' => $option['dump_volume'],
            'last_volume' => $option['last_volume'],
            'dump' => $option['dump']
        ];
    }
}

// ✅ Все уникальные miners
$allMiners = array_keys($allOptions);
$minersLeft = $allMiners; // копия для отслеживания

// Сортируем варианты внутри каждого дампа по убыванию score
foreach ($allDumps as &$dumpOptions) {
    usort($dumpOptions, function ($a, $b) {
        return $b['score'] <=> $a['score'];
    });
}
unset($dumpOptions);
// Инициализация
//  получение всех имен забоев
$allMinersNames = Miner::whereIn('id', array_keys($allOptions))
    ->pluck('name_miner', 'id')
    ->toArray();


$assignmentsPoints = [];
$minersLeft = $allMiners;
$round = 1;
$Distancies = 0;
// Цикл распределения
do {
    $minersAssignedThisRound = [];

    foreach ($allDumps as $dumpId => &$dumpOptions) {
        if (empty($dumpOptions)) {
            continue; // нет вариантов
        }

        foreach ($dumpOptions as $idx => $option) {
            $minerId = $option['miner_id'];

            // Если miner нуждается в дампе и не назначен в этом раунде
            if (in_array($minerId, $minersLeft) &&!in_array($minerId, $minersAssignedThisRound)) {
                // Назначаем дамп miner'у
                $assignmentsPoints[$minerId][] = [
                    'dump_id' => $dumpId,
                    'score' => $option['score'],
                    'distance' => $option['distance'],
                    'travel_time' => $option['travel_time'],
                    'volume' => $option['volume'],
                    'dump_volume' => $option['dump_volume'],
                    'last_volume' => $option['last_volume'],
                    'dump' => $option['dump'],
                    'assigned_round' => $round,
                    //  ПОЛЯ С ИМЕНАМИ:
                    'miner_name' => $allMinersNames[$minerId]?? "Забой #{$minerId}",
                    'miner_id' => $minerId,
                    'dump_name' => $option['dump']->name_dump?? "Дамп #{$dumpId}",
                    'assigned_round' => $round
                ];
                $Distancies += $option['distance'];
                $minersAssignedThisRound[] = $minerId;

                // Удаляем использованный вариант
                unset($dumpOptions[$idx]);
                $dumpOptions = array_values($dumpOptions);
                break; // переход к следующему дампу
            }
        }
    }

    // Убираем назначенных с списка minersLeft
    $minersLeft = array_diff($minersLeft, $minersAssignedThisRound);

    $round++;

    // Останавливаем по достижении лимита раундов или при пустом списке minersLeft
} while (!empty($minersLeft) && $round <= 10);

// ✅ Подсчёт статистики
$totalRoutes = 0;
$assignedMiners = count($assignmentsPoints);
$totalScore = 0;
$bestOverallScore = 0;

// Идём по всем назначениям
foreach ($assignmentsPoints as $minerId => $minerRoutes) {
    $routeCount = count($minerRoutes);
    $totalRoutes += $routeCount;

    // Score для этого miner'а
    $minerTotalScore = 0;
    foreach ($minerRoutes as $route) {
        $minerTotalScore += $route['score'];
        if ($route['score'] > $bestOverallScore) {
            $bestOverallScore = $route['score'];
        }
    }
    $totalScore += $minerTotalScore;
}

// ✅ Статистика
$distributionStats = [
    'method' => 'распределение выполнено пропорционально с учетом приоритета расчитанного по объему на перегрузках и длине маршрута от каждого забоя',
    'avg_routes_per_dump' => round($totalRoutes / $countSuitableDumps, 1),
    'total_score' => round($totalScore, 1),
    'avg_score_per_miner' => round($totalScore / $assignedMiners, 1),
    'best_score' => round($bestOverallScore, 1),
    'average_distance' => $assignmentsPoints? round($Distancies / count($assignmentsPoints), 2): 0,
];


        // Группируем доступные зоны по породам (delivery=true)
        $zonesByRock = DB::table('zones')
            ->join('rock_zone', 'zones.id', '=', 'rock_zone.zone_id')  
            ->join('rocks', 'rock_zone.rock_id', '=', 'rocks.id')      
            //->where('zones.delivery', true)                            
            ->select(
                    'zones.id', 
                    'zones.name_zone', 
                    'zones.dump_id',          
                    'zones.volume',            
                    'zones.delivery',            
                    'rocks.name_rock as name_rock'  // ← name, а не name_rock
                )   
            ->orderBy('rocks.name_rock')                               
            ->orderBy('zones.name_zone')                                    
            ->get()
            ->groupBy('name_rock');      
            

// Работаем с плоским массивом всех зон
$allZones = $zonesByRock->flatten();

// Считаем общий объём для каждого dump'а
$dumpVolumes = $allZones
    ->groupBy('dump_id')
    ->map(function($zonesInDump, $dumpId) {
        $totalVolume = $zonesInDump->sum('volume');
        $zoneCount = $zonesInDump->count();
        $rockCount = $zonesInDump->groupBy('name_rock')->count();

        return [
            'total_volume' => $totalVolume,
            'zone_count' => $zoneCount,
            'rock_count' => $rockCount
        ];
        
    });

// ✅ НОВЫЙ КОД (ЧАСТЬ 1): $dumpVolumesArray БЕЗ orderBy + ПОРЯДОК ДАМПОВ
$dumpVolumesArray = DB::table('zones')
    ->select('dump_id', DB::raw('SUM(volume) as total_volume'))
    ->whereNotNull('volume')
    ->groupBy('dump_id')
    ->having('total_volume', '>=', 0)
    ->pluck('total_volume', 'dump_id')
    ->toArray();

// ✅ СОЗДАЁМ ПОРЯДОК ДАМПОВ ПО ОБЁМУ
$sortedDumpVolumes = $dumpVolumesArray;
asort($sortedDumpVolumes);  // Сортируем по объёму: от меньшего к большему
$dumpOrder = array_keys($sortedDumpVolumes);  // массив значений dump_id 

// ✅ СОЗДАЁМ ПОЗИЦИИ ДАМПОВ (для usort)
$dumpPositions = [];
foreach ($dumpOrder as $index => $dumpId) {
    $dumpPositions[$dumpId] = $index;  
}

//  Добавляем объем каждой зоне
$zonesWithWeight = $allZones->map(function($zone) use ($dumpVolumesArray) {
    $zone->dump_total_volume = $dumpVolumesArray[$zone->dump_id]?? 0;
    return $zone;
});

// 
$zonesArray = $zonesWithWeight->toArray();
usort($zonesArray, function($a, $b) use ($dumpPositions) {
    $posA = $dumpPositions[$a->dump_id]?? 999;
    $posB = $dumpPositions[$b->dump_id]?? 999;
    return $posA - $posB;
});
$sortedZones = collect($zonesArray)->values();


$sortedZonesByRock = collect();
foreach ($sortedZones->groupBy('name_rock') as $rockName => $zonesForRock) {
    $zonesArray = $zonesForRock->toArray();
    usort($zonesArray, function($a, $b) use ($dumpPositions) {
        $posA = $dumpPositions[$a->dump_id]?? 999;
        $posB = $dumpPositions[$b->dump_id]?? 999;
        return $posA - $posB;
    });
    $sortedZonesByRock[$rockName] = collect($zonesArray);

    // // Простой лог
    // $firstDump = $sortedZonesByRock[$rockName]->first()->dump_id?? 'НЕТ';
 
}


//  Итоговые статистики
$totalVolume = $sortedZones->sum('volume');

$dumpOrder = [];
foreach($dumpOrder as $i => $dumpId) {
    $vol = $dumpVolumesArray[$dumpId];
   
}

// ✅ Краткий вывод по породам

$sortedZonesByRock->each(function($zones, $rock) {
    $total = $zones->sum('volume');
    $dumps = $zones->pluck('dump_id')->unique()->count();
   
});

// ✅ Готовый результат
$finalResult = [
   // 'zones_by_rock' => $sortedZonesByRock,
    'total_volume' => $totalVolume,
    'dump_order' => $dumpOrder
];


        // ПРОСТАЯ ЗАГРУЗКА DUMPS
        $dumps = Dump::with(['zones' => function($q) {
        $q->where('delivery', true);  // Только доступные зоны
        }])->get();
        $dumpCapacities = []; 

        foreach ($dumps as $dump) {
            $totalVolume = 0;
            foreach ($dump->zones as $zone) {
                $totalVolume += $zone->volume?? 0;  // ← Защита от null
            }
            $dumpCapacities[$dump->id] = $totalVolume;  // ← Сохраняем результат
        }
            $totalCapacity = array_sum($dumpCapacities);  // Общая ёмкость
            $dumpCount = count($dumpCapacities);          // Количество dumps
            // РАСЧЁТ СРЕДНЕЙ ЁМКОСТИ 
            $averageCapacity = $dumpCount > 0? round($totalCapacity / $dumpCount): 0;
    
    // Проверяем данные из предыдущих частей
    $availableZonesByRock = $zonesByRock;
    $minerToDumpDistances = $distances;
    $dumpCapacitiesArray = $dumpCapacities;

                // ФИНАЛЬНАЯ СТАТИСТИКА
        $stats['total_distance_km'] = $bestDistancies;
        $stats['total_time_hours'] = round($totalTime, 2);
        $stats['average_distance'] = $assignments? round($bestDistancies / count($assignments), 2): 0;
        $stats['average_time'] = $assignments? round($totalTime / count($assignments), 2): 0;
        $stats['distribution'] = $distribution;
        
        $stats['total_dump_capacity'] = $totalCapacity;      // Общая ёмкость
        $stats['dump_count'] = $dumpCount;                   // Количество перегрузок
        $stats['total_volume'] = $finalResult['total_volume'];
        
        $stats['total_zones'] = Zone::count();
        $stats['zones_by_rock'] = $sortedZonesByRock;
        $stats['dump_order'] = $dumpVolumesArray;
        $stats['total_available_zones'] = $zonesByRock->sum(fn($group) => $group->count());
        $stats['selected_mode'] = $mode;
        $stats['mode_name'] = match($mode) {
            'volume' => 'Приоритет по объёму',
            'distance' => '🏃 Приоритет по расстоянию', 
            'balance' => '⚖️ Баланс объёма и расстояния (30/70)',
            default => '⚖️ Баланс'
        };
        $stats['total_miners'] = Miner::count();
        $stats['total_dumps'] = Dump::count();
        // Сортируем по score (от большего к меньшему)
        uasort($assignments, function($a, $b) {
            return ($b['score']?? 0) <=> ($a['score']?? 0);
        });

        // ✅ СТАНОВИТСЯ (JSON для Livewire)
        if (request()->ajax() || request()->wantsJson()) {
            return response()->json([
                'distribution' => $assignmentsPoints,
                'stats' => $distributionStats,
                'mode' => $mode
            ]);
        }
        // Передаём данные в представление
        return view('dump.distribution', compact('assignmentsPoints', 'distributionStats', 'allMiners','stats', 'assignments', 'mode', 'allOptions', 'activeZonesOnly' ));



    }



}
