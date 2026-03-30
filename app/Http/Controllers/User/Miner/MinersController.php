<?php

namespace App\Http\Controllers\User\Miner;

use App\Http\Controllers\Controller;
use App\Models\Miner;
use App\Models\Rock;
use Illuminate\Http\Request;

class MinersController extends Controller
{
    /**
     * Список всех забоев
     */
    public function index()
    {
        $miners = Miner::with(['currentRock', 'rocks'])
            ->withCount('orders')
            ->orderBy('name_miner')
            ->get();

        return view('miners.index', compact('miners'));
    }

    /**
     * Форма создания забоя
     */
    public function create()
    {
        $rocks = Rock::orderBy('name_rock')->get();
        return view('miners.create', compact('rocks'));
    }

    /**
     * Создать забой
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name_miner' => 'required|string|max:255|unique:miners,name_miner',
            'capacity_per_trip' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
        ]);

        $miner = Miner::create([
            'name_miner' => $data['name_miner'],
            'capacity_per_trip' => $data['capacity_per_trip'] ?? null,
            'description' => $data['description'] ?? null,
            'active' => true,
        ]);

        return redirect()->route('miners.index')
            ->with('success', "Забой '{$miner->name_miner}' создан");
    }

    /**
     * Показать забой
     */
    public function show(Miner $miner)
    {
        $miner->load(['rocks', 'orders.dump', 'distances.dump']);
        return view('miners.show', compact('miner'));
    }

    /**
     * Форма редактирования забоя (с породами!)
     */
    public function edit(Miner $miner)
    {
        $miner->load('rocks');
        $rocks = Rock::orderBy('name_rock')->get();
        return view('miners.edit', compact('miner', 'rocks'));
    }

    /**
     * Обновить забой
     */
    public function update(Request $request, Miner $miner)
    {
        $data = $request->validate([
            'name_miner' => 'required|string|max:255',
            'capacity_per_trip' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'active' => 'boolean',
        ]);

        $miner->update([
            'name_miner' => $data['name_miner'],
            'capacity_per_trip' => $data['capacity_per_trip'] ?? null,
            'description' => $data['description'] ?? null,
            'active' => $data['active'] ?? $miner->active,
        ]);

        // Породы обновляются автоматически через syncWithoutDetaching при работе экскаваторщика

        return redirect()->route('miners.index')
            ->with('success', "Забой '{$miner->name_miner}' обновлён");
    }

    /**
     * Удалить забой
     */
    public function destroy(Miner $miner)
    {
        if ($miner->orders()->count() > 0) {
            return redirect()->route('miners.index')
                ->with('error', "Нельзя удалить забой с привязанными маршрутами");
        }

        $name = $miner->name_miner;
        $miner->rocks()->detach();
        $miner->delete();

        return redirect()->route('miners.index')
            ->with('success', "Забой '{$name}' удалён");
    }
}
