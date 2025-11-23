<?php

namespace App\Http\Controllers\User\Miner;  // ← Твоё единственное число

use App\Http\Controllers\Controller;
use App\Models\Miner;
use App\Models\Dump;
use Illuminate\Http\Request;

class MinersController extends Controller
{
    /**
     * Отображение списка майнеров с расстояниями до дампов
     */
    public function index()
    {
        $miners = Miner::with('dumps')  // Загружаем связанные дампы
                       ->orderBy('created_at', 'desc')
                       ->paginate(15);

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
        Miner::create($validated);

        return redirect()->route('miners.index')
            ->with('success', "Оборудование '{$validated['name_miner']}' довавлено!Установите маршруты для работы в системе распределения");
    }
    public function show(Miner $miner)
    {
        $miner->load('dumps');
        return view('miners.show', compact('miner'));
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
        $oldName = $miner->name_miner;  // Сохраняем старое имя для вывода в сообщении

            // Проверяем, изменилось ли имя
        if ($oldName!== $validated['name_miner']) {
            $message = "Забой '{$oldName}' изменен на '{$validated['name_miner']}'!";
        } else {
            $message = "Данные забоя '{$validated['name_miner']}' обновлёны!";
        }

        $miner->update([
            'name_miner' => $validated['name_miner'],
            'active' => $validated['active']?? false,
        ]);

        // Сохраняем/обновляем расстояния
        if (isset($validated['dump_distances'])) {
            foreach ($validated['dump_distances'] as $dumpId => $distance) {
                if ($distance > 0) {
                    $miner->dumps()->syncWithoutDetaching([
                        $dumpId => ['distance_km' => $distance]
                    ]);
                } else {
                    $miner->dumps()->detach($dumpId);
                }
            }
        }

            // Используем новое имя для сообщения
    
        return redirect()->route('miners.index')->with('success', $message);
    }

    public function destroy(Miner $miner)
    {
        $miner->delete();

        return redirect()->route('miners.index')
            ->with('success', 'информация удалёна!');
    }
}

   