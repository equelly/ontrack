<?php

namespace App\Http\Controllers\User\Dump;

use App\Http\Controllers\Controller;
use App\Models\Dump;
use App\Models\Rock;

class EditController extends Controller
{
    /**
     * Показать форму редактирования отвала с зонами и породами
     */
    public function __invoke(Dump $dump)
    {
        $dump->load(['zones.rocks']);
        $allRocks = Rock::orderBy('name_rock')->get();

        return view('dump.edit', compact('dump', 'allRocks'));
    }
}
