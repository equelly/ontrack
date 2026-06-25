<?php

namespace App\Http\Controllers\User\Rock;

use App\Http\Controllers\Controller;
use App\Models\Rock;
use App\Models\Zone;
use App\Models\Miner;
use Illuminate\Http\Request;

class IndexController extends Controller
{
    /**
     * Список всех пород с их связями
     */
    public function __invoke()
    {
        $rocks = Rock::withCount(['zones'])
            ->orderBy('name_rock')
            ->get();

        // Статистика по связям
        $stats = [
            'total_rocks' => $rocks->count(),
            'linked_to_zones' => $rocks->where('zones_count', '>', 0)->count(),
            'linked_to_miners' => $rocks->where('miners_count', '>', 0)->count(),
            'unlinked' => $rocks->where('zones_count', 0)->where('miners_count', 0)->count(),
        ];

        return view('rocks.index', compact('rocks', 'stats'));
    }
}
