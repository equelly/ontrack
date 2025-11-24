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
        $miners = Miner::with(['lastUpdater', 'dumps'])  // Загружаем связанные дампы и пользователя
                       ->orderBy('last_updated_at', 'desc')  
                       ->orderBy('created_at', 'desc')
                       ->paginate(15);

                // 2. Для каждого майнера — один SQL-запрос с JOIN и сортировкой!
    $miners->getCollection()->transform(function ($miner) {
        $dumps = DB::table('dumps')
            ->leftJoin('miner_dump_distances', function($join) use ($miner) {
                $join->on('dumps.id', '=', 'miner_dump_distances.dump_id')
                     ->where('miner_dump_distances.miner_id', '=', $miner->id);
            })
            ->select('dumps.*', 'miner_dump_distances.distance_km')
            ->orderBy('miner_dump_distances.distance_km', 'asc')  // ← СОРТИРОВКА!
            ->orderBy('dumps.id')
            ->get();

        $miner->dumps = $dumps;  // Заменяем на отсортированные дампы
        return $miner;
    });

        return view('miners.index', compact('miners'));
    }
    
  public function create()
    {
        return view('miners.create');
    }

    public function store(Request $request)
    {
        
        $validated = $request->validate([
            'name_miner' => 'required|string|max:255',
            'active' => 'boolean',

        ]);
        Miner::firstOrCreate($validated);

        return redirect()->route('miners.index')
            ->with('success', "Оборудование '{$validated['name_miner']}' добавлено!Установите маршруты для работы в системе распределения");
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


    //     public function update(Request $request, Miner $miner)
    // {
    //     $validated = $request->validate([
    //         'name_miner' => 'required|string|max:255',
    //         'active' => 'boolean',
    //         'dump_distances' => 'array',
    //         'dump_distances.*' => 'nullable|numeric|min:0|max:1000',
    //     ]);

    //         // Обновляем ВСЕ расстояния одной формой
    //     if (isset($validated['dump_distances'])) {
    //         foreach ($validated['dump_distances'] as $dumpId => $distance) {
    //             if ($distance > 0) {
    //                 $miner->dumps()->syncWithoutDetaching([
    //                     $dumpId => ['distance_km' => $distance]
    //                 ]);
    //             } else {
    //                 $miner->dumps()->detach($dumpId);
    //             }
    //         }
    //     }

    //     $oldName = $miner->name_miner;  // Сохраняем старое имя для вывода в сообщении

    //         // Проверяем, изменилось ли имя
    //     if ($oldName!== $validated['name_miner']) {
    //         $message = "Забой '{$oldName}' изменен на '{$validated['name_miner']}'!";
    //     } else {
    //         $message = "Данные забоя '{$validated['name_miner']}' обновлёны!";
    //     }

    //     $miner->update([
    //         'name_miner' => $validated['name_miner'],
    //         'active' => $validated['active']?? false,
    //     ]);

    //     // Сохраняем/обновляем расстояния
    //     if (isset($validated['dump_distances'])) {
    //         foreach ($validated['dump_distances'] as $dumpId => $distance) {
    //             if ($distance > 0) {
    //                 $miner->dumps()->syncWithoutDetaching([
    //                     $dumpId => ['distance_km' => $distance]
    //                 ]);
    //             } else {
    //                 $miner->dumps()->detach($dumpId);
    //             }
    //         }
    //     }

    //         // Используем новое имя для сообщения
    
    //     return redirect()->route('miners.index')->with('success', $message);
    // }
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

    // Обновляем майнера (аудит сработает автоматически через boot()!)
    $miner->update([
        'name_miner' => $validated['name_miner'],
        'active' => $validated['active']?? false,
    ]);

    // 🆗 Считаем изменения расстояний
    if (isset($validated['dump_distances'])) {
        foreach ($validated['dump_distances'] as $dumpId => $distance) {
            $existing = $miner->distances()->where('dump_id', $dumpId)->first();
            $oldDistance = $existing?->distance_km?? 0;

            if ($distance > 0) {
                // Добавляем/обновляем расстояние
                if ($oldDistance!= $distance) {
                    $miner->dumps()->syncWithoutDetaching([
                        $dumpId => ['distance_km' => $distance]
                    ]);
                    $distanceChanges++;
                }
            } else {
                // Удаляем расстояние, если было
                if ($oldDistance > 0) {
                    $miner->dumps()->detach($dumpId);
                    $distanceChanges++;
                }
            }
        }
    }

    //  Формируем информативное сообщение с аудитом
    $newName = $validated['name_miner'];
    $newActive = $validated['active']?? false;
    $user = auth()->user()?->name?? 'Система';
    $time = now()->format('H:i');

    $changes = [];

    // Проверяем изменение имени
    if ($oldName!== $newName) {
        $changes[] = "название: '{$oldName}' → '{$newName}'";
    }

    // Проверяем изменение статуса
    if ($oldActive!== $newActive) {
        $status = $newActive? 'в работе': 'не в работе';
        $changes[] = "статус изменен: теперь → {$status}";
    }

    // Проверяем изменения расстояний
    if ($distanceChanges > 0) {
        $changes[] = "обновлены расстояния для {$distanceChanges} маршрутов";
    }

    // Формируем финальное сообщение
    if (empty($changes)) {
        $message = "Изменены данные забоя '{$newName}'";
    } else {
        $changesList = implode(', ', $changes);
        $message = "Забой '{$newName}' обновлён: {$changesList}";
    }

    $message.= " 👤 изменения внесены: {$user} • в {$time}";

    return redirect()->route('miners.index')->with('success', $message);
}


    public function destroy(Miner $miner)
    {
        $miner->delete();

        return redirect()->route('miners.index')
            ->with('success', 'информация удалёна!');
    }
}

   