<?php

namespace App\Http\Controllers\User\Dump;

use App\Http\Controllers\Controller;
use App\Models\Dump;
use Illuminate\Http\Request;

class IndexController extends Controller
{
    /**
     * Показать список всех отвалов с их зонами
     */
    public function __invoke(Request $request)
    {
        $dumps = Dump::with(['zones.rocks'])
            ->withCount('zones')
            ->get();

        // Если нужен детальный вид распределения
        if ($request->has('detailed')) {
            $dumps->load(['zones' => function ($query) {
                $query->withCount('truckTrips');
            }]);
        }

        return view('dump.index', compact('dumps'));
    }
}
