<?php

namespace App\Http\Controllers\User\Dump;

use App\Http\Controllers\Controller;
use App\Models\Dump;
use Illuminate\Http\Request;

class ShowController extends Controller
{
    /**
     * Показать детали отвала
     */
    public function __invoke(Dump $dump)
    {
        $dump->load(['zones.rocks', 'orders.miner', 'orders.dump']);

        return view('dump.show', compact('dump'));
    }
}
