<?php

namespace App\Http\Controllers\User\Dump;

use App\Http\Controllers\Controller;
use App\Models\Dump;
use Illuminate\Http\Request;

class StoreController extends Controller
{
    /**
     * Создать новый отвал
     */
    public function __invoke(Request $request)
    {
        $data = $request->validate([
            'name_dump' => 'required|string|max:255',
        ]);

        $dump = Dump::create($data);

        return redirect()->route('dump.edit', $dump->id)
            ->with('success', 'Отвал создан. Добавьте зоны и породы.');
    }
}
