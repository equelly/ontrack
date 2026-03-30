<?php

namespace App\Http\Controllers\User\Rock;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CreateController extends Controller
{
    public function __invoke()
    {
        return view('rocks.create');
    }
}