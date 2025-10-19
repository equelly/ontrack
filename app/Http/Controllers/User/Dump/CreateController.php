<?php

namespace App\Http\Controllers\User\Dump;

use Illuminate\Routing\Controller as BaseController;
use App\Models\Mashine;
use App\Models\Set;

class CreateController extends BaseController
{
    public function __invoke(){

        $sets = Set::all();
        $mashines = Mashine::all();
          return view('order.create', compact('sets', 'mashines'));
        
    }
}
