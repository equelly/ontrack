<?php

namespace App\Http\Controllers\User\Dump;

use App\Http\Controllers\Controller;
use App\Models\Miner;
use App\Models\Dump;
use App\Models\MinerDumpDistance;
use App\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class DistributionController extends Controller
{

        public function index(Request $request)
    {
// Чтение параметров и базовая статистика
    $mode = $request->input('mode', 'balance'); // volume, distance, balance

               // ✅ ВАЛИДАЦИЯ (только разрешённые режимы)
        $allowedModes = ['balance', 'volume', 'distance'];
        if (!in_array($mode, $allowedModes)) {
            $mode = 'balance'; // по умолчанию
        }

        // ✅ ЛОГИРОВАНИЕ ДЛЯ ОТЛАДКИ (временно)
        Log::info('Выбран режим сортировки: '. $mode);

        
        $availableZones = Zone::where('delivery', true)->get(['id', 'name_zone']);
        
        
        
        // БАЗОВАЯ СТАТИСТИКА

        // Группируем доступные зоны по породам (delivery=true)
        $zonesByRock = DB::table('zones')
            ->join('rock_zone', 'zones.id', '=', 'rock_zone.zone_id')  
            ->join('rocks', 'rock_zone.rock_id', '=', 'rocks.id')      
            ->where('zones.delivery', true)                            
            ->select(
                    'zones.id', 
                    'zones.name_zone', 
                    'zones.dump_id',          
                    'zones.volume',            
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
$dumpOrder = array_keys($sortedDumpVolumes);  // массив значений dump_id [5, 1, 2, 3, 6, 4]

// ✅ СОЗДАЁМ ПОЗИЦИИ ДАМПОВ (для usort)
$dumpPositions = [];
foreach ($dumpOrder as $index => $dumpId) {
    $dumpPositions[$dumpId] = $index;  // 5=>0, 1=>1, 2=>2, 3=>3, 6=>4, 4=>5
}

//  Добавляем объем каждой зоне
$zonesWithWeight = $allZones->map(function($zone) use ($dumpVolumesArray) {
    $zone->dump_total_volume = $dumpVolumesArray[$zone->dump_id]?? 0;
    return $zone;
});

// // ✅ ТЕСТ 1: Берём ВСЕ ЗОНЫ и сортируем usort()
// $allZonesArray = $allZones->toArray();
// Log::info("🔍 ДО usort(): первые дампы: ". 
//     collect($allZonesArray)->take(3)->pluck('dump_id')->implode(', '));

// // ✅ ПРОСТОЙ usort() ДЛЯ ВСЕХ ЗОН
// usort($allZonesArray, function($a, $b) use ($dumpPositions) {
//     $posA = $dumpPositions[$a->dump_id]?? 999;  // Большое число для неизвестных
//     $posB = $dumpPositions[$b->dump_id]?? 999;
//     return $posA - $posB;  // Простое вычитание вместо <=> (PHP 7+)
// });

// $sortedAllZonesTest = collect($allZonesArray);
// Log::info("🔄 ПОСЛЕ usort(): первые дампы: ". 
//     $sortedAllZonesTest->take(3)->pluck('dump_id')->implode(', '));

// // ✅ ТЕСТ 2: Проверяем, что dump #5 идёт первым
// $firstDumpId = $sortedAllZonesTest->first()->dump_id?? 'НЕТ';
// Log::info("🎯 ПЕРВЫЙ ДАМП: #". $firstDumpId. " (должен быть 5!)");

// // ✅ ТЕСТ 3: Считаем сколько зон для каждого дампа
// $dumpCounts = $sortedAllZonesTest->groupBy('dump_id')->map->count()->toArray();
// Log::info("📊 ЗОН ПО ДАМПАМ: ". json_encode($dumpCounts));

// ✅ МИКРО-ШАГ 1: Заменяем sortBy() на usort()
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

    // Простой лог
    $firstDump = $sortedZonesByRock[$rockName]->first()->dump_id?? 'НЕТ';
 
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
    'zones_by_rock' => $sortedZonesByRock,
    'total_volume' => $totalVolume,
    'dump_order' => $dumpOrder
];




        // Загружаем расстояния между miners и dumps
        $distances = MinerDumpDistance::with([
            'miner', 
            // 'dump.zones' => function($q) {
            //     $q->where('delivery', true);  // Только доступные зоны
            //}
        ])->get()->groupBy('miner_id');
        // Добавляем в статистику
        $stats['total_miners_with_distances'] = $distances->keys()->count();
    // ← ЧАСТЬ 1/4: ПОДГОТОВКА УНИВЕРСАЛЬНОГО ЦИКЛА
   
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
   
    $bestDistancies = 0;
    $stats['total_assignments'] = 0;
    // Базовый цикл: проходим по всем miners

    foreach ($distances as $minerId => $minerDistances) {

        $miner = $minerDistances->first()->miner;

        // Фильтруем только dumps с зонами 
        $suitableDumps = $minerDistances
            ->filter(function($record) {
                return $record->dump->zones;//->isNotEmpty()
            })
            ->map(function($record) {
                $dump = $record->dump;
                $totalZoneVolume = $dump->zones->sum('volume');

                return [
                    'dump' => $dump,
                    'distance' => $record->distance_km,
                    'total_zone_volume' => $totalZoneVolume,
                    //емкость перегрузки (вместимость всех зон) принята условно 60 
                    //-конкретно можно для каждой зоны создать колонку capacity в табл. zones и затем ссумировать их как 'total_zone_volume'
                    'dump_volume' => $dump->capacity?? 60
                ];
            });
            // ОТЛАДКА 1: КАКОЕ РАССТОЯНИЕ У КАЖДОГО MINER'А ДО КАЖДОГО DUMP'А
        //Log::info("🔍 Miner '{$miner->name_miner}' (ID: {$minerId}): доступные dumps с расстояниями:");
        foreach ($suitableDumps as $option) {
            $dumpName = $option['dump']->name_dump;
            $distance = $option['distance'];

        }



        if ($suitableDumps->isEmpty()) {
          
            continue;
        }

                // ← ЧАСТЬ 2.1/4: ПОДГОТОВКА ЛОГИКИ РЕЖИМОВ
        $suitableDumpCount = $suitableDumps->count();
        $minerName = $miner->name_miner?? 'не установлен';
        // Проверяем режим и логируем


if (!empty($suitableDumps)) {
    $dumpOptions = [];  

    // ✅ ОБЩИЕ РАСЧЁТЫ (для всех режимов)
    foreach ($suitableDumps as $index => $option) {
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
            $inverseVolume = (1 / ($volume + 1)) * 100; // 1/объём (маленький = большой score)
            $distancePenalty = $distance * 3; // небольшой штраф за расстояние
            $score = $inverseVolume - $distancePenalty;
        } else { // distance - ПРОСТО!
            // Score обратно пропорционален расстоянию
            $score = round((1 / ($distance + 0.1)) * 100, 2);
            // 0.1км = 1000 баллов, 1км = 100, 10км = 10
        }


        $dumpOptions[] = [
            'dump' => $option['dump'],
            'distance' => $distance,
            'total_zone_volume' => $volume,
            'total_available_zones' => $option['total_available_zones']?? 0,
            'score' => $score,
            'travel_time_hours' => round($travelTimeHours, 2),
            'dump_volume' => $dumpCapacity,
            'last_volume' => $dumpCapacity - $volume
        ];
    }

    // ✅ СОРТИРУЕМ (лучший первый)
    usort($dumpOptions, function($a, $b) {
        return $b['score'] <=> $a['score']; // По убыванию score
    });

    // ✅ БЕРЁМ ТОЛЬКО ПЕРВЫЙ (лучший!)
    if (!empty($dumpOptions)) {
        $bestOption = $dumpOptions[0];

        // ТВОЙ КОД СОЗДАНИЯ РЕЗУЛЬТАТА (как у тебя)
        $distribution[$minerId] = [
            'miner_name' => $miner->name_miner?? $minerId,
            'dump_id' => $bestOption['dump']->id,
            'name_dump' => $bestOption['dump']->name_dump,
            'total_available_zones' => $bestOption['total_available_zones'],
            'total_zone_volume' => $bestOption['total_zone_volume'],
            'distance_km' => $bestOption['distance'],
            'travel_time_hours' => $bestOption['travel_time_hours'],
            'dump_volume' => $bestOption['dump_volume'],
            'last_volume' => $bestOption['last_volume'],
            'score' => round($bestOption['score'], 2)
        ];

        $assignments[$minerId] = [$distribution[$minerId]];
        $bestDistancies += $bestOption['distance'];
        $totalTime += $bestOption['travel_time_hours'];
        $totalAssignments++;
        $stats['total_assignments']++;
    }
}

      

    }


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
        //$stats['total_assignments'] = count($dumpsWithScores);
        $stats['total_distance_km'] = $bestDistancies;
        $stats['total_time_hours'] = round($totalTime, 2);
        $stats['average_distance'] = $assignments? round($bestDistancies / count($assignments), 2): 0;
        $stats['average_time'] = $assignments? round($totalTime / count($assignments), 2): 0;
        $stats['distribution'] = $distribution;
        $stats['assignments'] = $assignments;
        $stats['total_dump_capacity'] = $totalCapacity;      // Общая ёмкость
        $stats['dump_count'] = $dumpCount;                   // Количество dumps
        $stats['average_dump_capacity'] = $averageCapacity;  // Средняя ёмкость
        $stats['available_zones'] = $availableZones;
        $stats['total_volume'] = $finalResult['total_volume'];
        
        $stats['total_zones'] = Zone::count();
        $stats['zones_by_rock'] = $sortedZonesByRock;
        $stats['dump_order'] = $dumpVolumesArray;
        $stats['total_available_zones'] = $zonesByRock->sum(fn($group) => $group->count());
        $stats['selected_mode'] = $mode;
        $stats['mode_name'] = match($mode) {
            'volume' => '📦 Приоритет по объёму',
            'distance' => '🏃 Приоритет по расстоянию', 
            'balance' => '⚖️ Баланс объёма и расстояния (30/70)',
            default => '⚖️ Баланс'
        };
        $stats['total_miners'] = Miner::count();
        $stats['total_dumps'] = Dump::count();

     
        // Передаём данные в представление
        return view('dump.distribution', compact('stats', 'assignments', 'zonesByRock', 'distances', 'mode' ));



    }



}
