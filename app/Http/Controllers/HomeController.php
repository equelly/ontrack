<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        $position = auth()->user()->position;

        switch ($position) {
            case 'driver':
                return redirect()->route('driver.panel');
            case 'dispatcher':
                return redirect()->route('dispatcher.index');
            case 'excavator_operator':
                return redirect()->route('excavator.index');
            case 'master':
                return redirect()->route('master');
            default:
                return redirect()->route('dump.index');
        }
    }
}