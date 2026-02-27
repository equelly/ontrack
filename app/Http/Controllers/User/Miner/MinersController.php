<?php

namespace App\Http\Controllers\User\Miner;  // ← Твоё единственное число

use App\Http\Controllers\Controller;
use App\Models\Miner;
use App\Models\Dump;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class MinersController extends Controller
{
    /**
     * Отображение списка майнеров с расстояниями до дампов
     */
public function index()
{
    $miners = Miner::with('lastUpdater')
                   ->orderBy('last_updated_at', 'desc')
                   ->orderBy('created_at', 'desc')
                   ->paginate(15);

    $minerIds = $miners->getCollection()->pluck('id');

    // Один запрос: dumps с distance для всех майнеров
    $dumpsData = DB::table('dumps')
        ->leftJoin('miner_dump_distances', 'dumps.id', '=', 'miner_dump_distances.dump_id')
        ->whereIn('miner_dump_distances.miner_id', $minerIds)
        ->select('dumps.*', 'miner_dump_distances.miner_id', 'miner_dump_distances.distance_km')
        ->orderBy('miner_dump_distances.miner_id')
        ->orderBy('miner_dump_distances.distance_km', 'asc')
        ->get()
        ->groupBy('miner_id');

    // Все dump IDs
    $allDumpIds = $dumpsData->flatten()->pluck('id');

    // Один запрос: active zones (delivery=1)
    $zonesData = DB::table('zones')
        ->whereIn('dump_id', $allDumpIds)
        ->where('delivery', 1)
        ->select('zones.*')
        ->get()
        ->groupBy('dump_id');

       // 3. Zone IDs для rocks
    $allZoneIds = $zonesData->flatten()->pluck('id');

    // Один запрос: rocks через pivot rock_zone (без quantity)
    $rocksData = DB::table('rocks')
        ->join('rock_zone', 'rocks.id', '=', 'rock_zone.rock_id')
        ->whereIn('rock_zone.zone_id', $allZoneIds)  // Только active zones
        ->select('rocks.*', 'rock_zone.zone_id')
        ->orderBy('rock_zone.zone_id')
        ->orderBy('rocks.id')
        ->get()
        ->groupBy('zone_id');  // ['zone_id' => Collection rocks]




    // Присвой данные майнерам
    $miners->getCollection()->each(function ($miner) use ($dumpsData, $zonesData, $rocksData) {
        $dumps = $dumpsData->get($miner->id, collect());

        $dumps->each(function ($dump) use ($zonesData, $rocksData) {
        $dump->zones = $zonesData->get($dump->id, collect());

            $dump->zones->each(function ($zone) use ($rocksData) {
                $zone->rocks = $rocksData->get((string) $zone->id, collect());

                // Нет pivot — просто флаг
                $zone->hasRocks = $zone->rocks->isNotEmpty();
            });

            $dump->hasActiveZones = $dump->zones->isNotEmpty();
        });


        

        $miner->dumps = $dumps->sortBy('distance_km')->values();
    });
    return view('miners.index', compact('miners'));
}

    
  public function create()
    {
        return view('miners.create');
    }


    public function show(Miner $miner)
    {
              
        // Сортируем дампы по расстоянию после загрузки
        $miner->dumps = $miner->dumps->sortBy('pivot.distance_km');

        // 🆗 ШАГ 2: Ищем пользователя по last_updated_by (отдельно!)
        $lastUpdater = null;
        if ($miner->last_updated_by) {
            // Ищем в таблице users по ID
            $lastUpdater = User::select('id', 'name', 'email', 'role')
                               ->find($miner->last_updated_by);

            // Если у тебя другая модель пользователей:
            // $lastUpdater = Admin::find($miner->last_updated_by);
        }

        // 🆗 ШАГ 3: Подсчитываем статистику (опционально)
        $stats = [
            'total_dumps' => $miner->dumps->count(),
            'dumps_with_distance' => $miner->dumps->whereNotNull('pivot.distance_km')->count(),
            'closest_distance' => $miner->dumps->whereNotNull('pivot.distance_km')->min('pivot.distance_km'),
        ];

        // 🆗 ШАГ 4: Передаём ВСЕ данные в представление
        return view('miners.show', compact('miner', 'lastUpdater', 'stats'));
    }
    

    public function edit(Miner $miner)
{
    // Загружаем все дампы с расстояниями для этого майнера
    $miner->load('dumps');

    // Или передаём все дампы для выбора
    $allDumps = Dump::all();

    return view('miners.edit', compact('miner', 'allDumps'));
}


    public function update(Request $request, Miner $miner)
    {
        $validated = $request->validate([
            'name_miner' => 'required|string|max:255',
            'active' => 'boolean',
            'dump_distances' => 'array',
            'dump_distances.*' => 'nullable|numeric|min:0|max:1000',
        ]);

        $oldName = $miner->name_miner;
        $oldActive = $miner->active;
        $distanceChanges = 0;

        // Обновляем майнера
        $miner->update([
            'name_miner' => $validated['name_miner'],
            'active' => $validated['active'] ?? false,
        ]);

        // Обновляем расстояния
        if (isset($validated['dump_distances'])) {
            foreach ($validated['dump_distances'] as $dumpId => $distance) {
                $existing = $miner->distances()->where('dump_id', $dumpId)->first();
                $oldDistance = $existing?->distance_km ?? 0;

                if ($distance > 0) {
                    if ($oldDistance != $distance) {
                        $miner->dumps()->syncWithoutDetaching([
                            $dumpId => [
                                'distance_km' => $distance,
                                'travel_time_hours' => $distance / 20  // ← ИСПРАВЛЕНО
                            ]
                        ]);
                        $distanceChanges++;
                    }
                } else {
                    if ($oldDistance > 0) {
                        $miner->dumps()->detach($dumpId);
                        $distanceChanges++;
                    }
                }
            }
        }

        // Формируем сообщение
        $newName = $validated['name_miner'];
        $newActive = $validated['active'] ?? false;
        $user = auth()->user()?->name ?? 'Система';
        $time = now()->format('H:i');

        $changes = [];

        if ($oldName !== $newName) {
            $changes[] = "название: '{$oldName}' → '{$newName}'";
        }

        if ($oldActive !== $newActive) {
            $status = $newActive ? 'в работе' : 'не в работе';
            $changes[] = "статус изменен: теперь → {$status}";
        }

        if ($distanceChanges > 0) {
            $changes[] = "обновлены расстояния для {$distanceChanges} маршрутов";
        }

        if (empty($changes)) {
            $message = "Изменены данные забоя '{$newName}'";
        } else {
            $changesList = implode(', ', $changes);
            $message = "Забой '{$newName}' обновлён: {$changesList}";
        }

        $message .= " 👤 изменения внесены: {$user} • в {$time}";

        return redirect()->route('miners.index')->with('success', $message);
    }


    public function destroy(Miner $miner)
    {
        $miner->delete();

        return redirect()->route('miners.index')
            ->with('success', 'информация удалёна!');
    }
}

   