<?php

namespace App\Http\Controllers\User\Dump;

use App\Http\Controllers\Controller;
use App\Models\Rock;
use Illuminate\Http\Request;

class CreateController extends Controller
{
    /**
     * Показать форму создания отвала
     */
    public function __invoke()
    {
        $rocks = Rock::all();

        return view('dump.create', compact('rocks'));
    }
}
