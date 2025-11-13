<?php

namespace App\Http\Controllers\User\Dump;

use App\Http\Controllers\Controller;
use App\Models\Miner;
use App\Models\Dump;
use App\Models\MinerDumpDistance;
use App\Models\Zone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class DistributionController extends Controller
{

        public function index()
    {
        // БАЗОВАЯ СТАТИСТИКА
        $stats = [
            'total_miners' => Miner::count(),
            'total_dumps' => Dump::count(),
            'total_zones' => Zone::count(),
        ];

        // Группируем доступные зоны по породам (delivery=true)
        $zonesByRock = DB::table('zones')
            ->join('rock_zone', 'zones.id', '=', 'rock_zone.zone_id')  
            ->join('rocks', 'rock_zone.rock_id', '=', 'rocks.id')      
            ->where('zones.delivery', true)                            
            ->select('zones.id', 'zones.name_zone', 'rocks.name_rock')   
            ->orderBy('rocks.name_rock')                               
            ->orderBy('zones.name_zone')                                    
            ->get()
            ->groupBy('name_rock');                                   

        $stats['zones_by_rock'] = $zonesByRock;
        $stats['total_available_zones'] = $zonesByRock->sum(fn($group) => $group->count());

        // Загружаем расстояния между miners и dumps
        $distances = MinerDumpDistance::with([
            'miner', 
            'dump.zones' => function($q) {
                $q->where('delivery', true);  // Только доступные зоны
            }
        ])->get()->groupBy('miner_id');

        // Инициализируем статистику маршрутов
        $totalDistance = 0;
        $totalTime = 0;

        // Пока пустой массив для назначений
        $assignments = [];
        $distribution = [];
        // Текущие объёмы дампов из связанных zones (одной строкой!)
        $dumpLoad = Dump::with('zones')->get()->mapWithKeys(function ($dump) {
            return [$dump->id => $dump->zones->sum('volume')];
        })->toArray();

        $allMiners = Miner::pluck('name_miner', 'id')->toArray();
        $allDumps = Dump::pluck('name_dump', 'id')->toArray();

        foreach ($distances as $minerId => $minerDistances) {
            $miner = $minerDistances->first()->miner;
            
            // Для каждого miner'а: варианты dump'ов из расстояний
            $suitableDumps = $minerDistances
                ->filter(function($record) {
                    // Только dump'ы с зонами
                    return $record->dump->zones->isNotEmpty();
                })
                ->map(function($record) {
                    $dump = $record->dump;
                    $totalZoneVolume = $dump->zones->sum('volume');  // сумма объемов всех зон

                    return [
                        'dump' => $dump,
                        'distance' => $record->distance_km,
                        'total_zone_volume' => $totalZoneVolume,
                        //коэффициет приоритета 60% текущие объемы 40% расстояние завозки г/м на перегрузку от miner'а($minerId) с id из цикла
                        'priority_score' => (100 - $totalZoneVolume / 10) * 0.6 + ($record->distance_km) * 0.4//для учета приоритета завозки по объемам 80% и по расстоянию
                    ];
                });

            // вариант меньше объемов и на 20% по расстоянию
            $bestOption = $suitableDumps->sortBy('priority_score')->first();
            //сортировка по расстоянию
           // $bestOption = $suitableDumps->sortBy('distance')->first();

            if ($bestOption) {
                $dumpVolume = $bestOption['dump']->volume?? 60;
                //устанавливаем время одного рейса исходя из расстояния и средней скорости ~20км/ч
                $travelTimeHours = $bestOption['distance'] / 20;
                // ← ТВОЙ КОД: заполняем $distribution
                $distribution[$minerId] = [
                    'miner_name' => $miner->name_miner?? $minerId,
                    'dump_id' => $bestOption['dump']->id,
                    'name_dump' => $bestOption['dump']->name_dump,
                    'total_zone_volume' => $bestOption['total_zone_volume'],
                    'distance_km' => $bestOption['distance'],
                    'travel_time_hours' => round($travelTimeHours, 2),
                    'dump_volume' => $dumpVolume,
                    'last_volume' => 4,
                    'priority_score' => round($bestOption['priority_score'], 2)
                ];

                // ← ДОБАВЬ ЭТО: накапливаем статистику!
                $assignments[] = $distribution[$minerId];  // Добавляем назначение в массив

                $totalDistance += $bestOption['distance'];  // Суммируем расстояние

                // Время (пример: скорость 20 км/ч) = расстояние / 20 км/ч = часы)
                $totalTime += ($bestOption['distance'] / 20);
            }
        }


        

                // ФИНАЛЬНАЯ СТАТИСТИКА
        $stats['total_assignments'] = count($assignments);
        $stats['total_distance_km'] = $totalDistance;
        $stats['total_time_hours'] = round($totalTime, 2);
        $stats['average_distance'] = $assignments? round($totalDistance / count($assignments), 2): 0;
        $stats['average_time'] = $assignments? round($totalTime / count($assignments), 2): 0;
        $stats['distribution'] = $distribution;
        $stats['assignments'] = $assignments;

        
        $stats['performance'] = [
            'total_miners' => count($distances),
            'total_distances' => $distances->flatten()->count(),
            'avg_zone_volume' => collect($distribution)->avg('total_zone_volume')?? 0,
            'avg_distance' => collect($distribution)->avg('distance_km')?? 0
        ];

        // Передаём данные в представление
        return view('dump.distribution', compact('stats', 'assignments', 'zonesByRock', 'distances'));



    }



}
