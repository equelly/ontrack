<?php

namespace App\Http\Controllers\User\Rock;

use App\Http\Controllers\Controller;
use App\Models\Rock;
use Illuminate\Http\Request;

class DestroyController extends Controller
{
    public function __invoke(Rock $rock)
    {
        // Проверяем связи
        $zonesCount = $rock->zones()->count();
        $minersCount = $rock->miners()->count();

        if ($zonesCount > 0 || $minersCount > 0) {
            return redirect()->route('rocks.index')
                ->with('error', "Нельзя удалить породу '{$rock->name_rock}'. Она используется в {$zonesCount} зонах и {$minersCount} забоях.");
        }

        $name = $rock->name_rock;
        $rock->delete();

        return redirect()->route('rocks.index')
            ->with('success', "Порода '{$name}' удалена");
    }
}
