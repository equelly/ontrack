<?php

namespace App\Http\Controllers\User\Dumps;


use App\Models\Dump;
use App\Models\Zone;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class IndexController extends BaseController
{
    public function __invoke(Request $request){
    // НАЧИНАЕМ С ЧИСТОГО ЗАПРОСА
    $query = Dump::with(['zones.rocks', 'loaderZone.rocks']);

    // ПОЛУЧАЕМ РЕЖИМ ФИЛЬТРА ИЗ RADIO
    $filterMode = $request->get('filter_mode');
    $activeFilter = $filterMode?: 'all';


    // ПРИМЕНЯЕМ ФИЛЬТРЫ ПО РЕЖИМУ
    switch ($filterMode) {
    case 'all_delivery':
        // Все подготовленные к завозке (loader_zone_id не null)
        $query->whereHas('zones', function($zoneQuery) {
            $zoneQuery->where('delivery', true);
            });
        break;

    case 'ruda_delivery':
        $query->whereHas('zones', function($zoneQuery) {
            $zoneQuery->where('delivery', true)
                      ->whereHas('rocks', function($rockQuery) {
                          $rockQuery->where('name_rock', 'руда');
                      });
        });
        break;

    case 'has_ruda':
        // 🪨 Рудные перегрузки (дампы с рудой)
        $query->whereHas('zones.rocks', function($q) {
            $q->where('name_rock', 'руда');
        });
        break;

    case 'ruda_shipment':
        // Производится отгрузка руды (loader_zone_id + руда)
        $query->whereNotNull('loader_zone_id')
              ->whereHas('loaderZone.rocks', function($rockQuery) {
                  $rockQuery->where('name_rock', 'руда');
              });
        break;
    case 'priority_zones':
    // 🎯 БАЗОВЫЙ ФИЛЬТР: дампы с неподготовленными зонами с рудой
    $query->whereHas('zones', function($zoneQuery) {
        $zoneQuery->where('delivery', false)     // ← Неподготовленные
                  
                  ->whereHas('rocks', function($rockQuery) {
                      $rockQuery->where('name_rock', 'руда');  // ← С рудой
                  });
    });

    // 🎯 ПРОСТОЕ ПОДГРУЗКА ЗОН (БЕЗ СЛОЖНЫХ УСЛОВИЙ)
    $query->with(['zones' => function($zoneQuery) {
        $zoneQuery->where('delivery', false)
                  
                  ->with('rocks')  // ← Загружаем все породы, фильтр в Blade
                  ->orderBy('volume', 'ASC')  // ← Сортируем зоны по объёму
                  ->select('id', 'name_zone', 'dump_id', 'delivery', 'volume');
    }]);

    // 🎯 ПРОСТАЯ СОРТИРОВКА ДАМПОВ
    $query->orderBy('id', 'ASC');  // ← Пока просто по ID, сортировку зон в Blade

   
    break;

    default:
        // Все дампы (без фильтра)
        break;
}


    // ← 6. ВЫПОЛНЯЕМ ЗАПРОС ОДИН РАЗ!
    $dumps = $query->get();

        // суммируем объёмы зон для каждого дампа
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

   
        return view('dump.index', compact('dumps', 'sortedDumps', 'activeFilter'));
        
    }
}
