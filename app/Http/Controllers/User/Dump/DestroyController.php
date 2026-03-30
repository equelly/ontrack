<?php

namespace App\Http\Controllers\User\Dump;

use App\Http\Controllers\Controller;
use App\Models\Dump;
use Illuminate\Http\Request;

class DestroyController extends Controller
{
    /**
     * Удалить отвал
     */
    public function __invoke(Dump $dump)
    {
        // Проверяем, есть ли связанные данные
        if ($dump->zones()->count() > 0) {
            return redirect()->route('dump.index')
                ->with('error', 'Нельзя удалить отвал с зонами. Сначала удалите зоны.');
        }

        if ($dump->orders()->count() > 0) {
            return redirect()->route('dump.index')
                ->with('error', 'Нельзя удалить отвал с привязанными маршрутами.');
        }

        $dump->delete();

        return redirect()->route('dump.index')
            ->with('success', 'Отвал удалён');
    }
}
