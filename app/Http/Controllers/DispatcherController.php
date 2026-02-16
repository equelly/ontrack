<?php

namespace App\Http\Controllers;

use App\Models\Truck;
use Illuminate\Http\Request;

class DispatcherController extends Controller
{
    public function index()
    {
        // Загружаем все грузовики и их текущие маршруты
        $trucks = Truck::with('currentOrder')->get();

        return view('dispatcher.index', [
            'trucks' => $trucks,
        ]);
    }
}

