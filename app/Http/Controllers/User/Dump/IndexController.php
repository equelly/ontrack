<?php

namespace App\Http\Controllers\User\Dump;


use App\Models\Dump;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class IndexController extends BaseController
{
    public function __invoke(Request $request){

        
                // Базовый запрос с фильтром по завозке и eager loading уменьшает количество запросов 
       
        $query = Dump::with(['zones.rocks', 'loaderZone.rocks']);


        // ФИЛЬТР ПО ЗАВОЗКЕ
        if ($request->filled('delivery') && $request->boolean('delivery')) {
            $query->whereHas('zones', function($q) {  // whereHas() — стандартный метод Laravel для фильтрации по связанным моделям
                $q->where('delivery', true);
            });
        }
            // ФИЛЬТР ПО РУДЕ
        if ($request->filled('has_rock') && $request->boolean('has_rock')) {
            $query->whereHas('zones.rocks', function($q) {
                $q->where('name_rock', 'руда');
            });
        }
        
        // ФИЛЬТР: ОТГРУЗКА РУДЫ (loader_zone_id не null И рудой)
        
        if ($request->filled('rock_shipment') && $request->boolean('rock_shipment')) {
            // ← ОТЛАДКА: сколько дампов с loader_zone_id
            $dumpsWithShipment = Dump::whereNotNull('loader_zone_id')->count();

            // ← ФИЛЬТР: дампы с назначенной зоной отгрузки
            $query->whereNotNull('loader_zone_id')
                ->whereHas('loaderZone.rocks', function($rockQuery) {
                    $rockQuery->where('name_rock', 'руда');  // ← И есть руда
                });

            // ← ОТЛАДКА: результат фильтра
            $filteredCount = $query->count();
            Log::info("🚚 ФИЛЬТР ОТГРУЗКИ: найдено дампов = ". $filteredCount);
        }



        $dumps = $query->get();


        // ← ДОБАВЬ: суммируем объёмы зон для каждого дампа
        $dumpsWithVolumes = $dumps->map(function ($dump) {
            // Общий объём (все зоны)
            $totalVolume = $dump->zones->sum('volume');

            // Объём руды (только зоны с породой "руда")
            $rockVolume = 0;
            $hasRockZones = false; // ← НОВЫЙ ФЛАГ!

            foreach ($dump->zones as $zone) {
                // Проверяем, есть ли в зоне порода "руда"
                $hasRockInZone = $zone->rocks->where('name_rock', 'руда')->count() > 0;

                if ($hasRockInZone) {
                    $hasRockZones = true; // ← Отмечаем, что зона с рудой найдена
                    $rockVolume += $zone->volume; // Добавляем объём зоны
                }
            }


            return [
                'dump' => $dump,
                'total_volume' => $totalVolume,
                'rock_volume' => $rockVolume, // поле с объемом руды
                'has_rock_zones' => $hasRockZones, // поле для проверки наличая зоны с рудой
                'zones_count' => $dump->zones->count(),
                'has_delivery' => $dump->zones->where('delivery', true)->count() > 0,
                'delivery_zones' => $dump->zones->where('delivery', true)->pluck('name_zone')->toArray(),
                // породы для зон завозки
                'delivery_zone_rocks' => $dump->zones
                    ->where('delivery', true)
                    ->map(function($zone) {
                        $rocks = $zone->rocks->pluck('name_rock')->toArray();
                        return [
                            'name' => $zone->name_zone,
                            'rocks' => $rocks
                        ];
                    })
                    ->values()
                    ->toArray()
                        ];
                    });
        

        $sortedDumps = $dumpsWithVolumes->sortBy(function ($item) {
            
        // ПЕРВЫЙ КРИТЕРИЙ: объём руды (от меньшего к большему)
        $rockVolume = $item['rock_volume'];

        // ВТОРОЙ КРИТЕРИЙ: общий объём (от меньшего к большему)
        $totalVolume = $item['total_volume'];

        // СОЗДАЁМ "КОД СОРТИРОВКИ": руда * 10000 + общий
        return $rockVolume * 10000 + $totalVolume;
    });

        
        return view('dump.index', compact('dumps', 'sortedDumps'));
        
    }
}
