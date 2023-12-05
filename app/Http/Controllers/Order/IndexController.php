<?php

namespace App\Http\Controllers\Order;

use Illuminate\Routing\Controller as BaseController;
use App\Models\Mashine;
use App\Models\Set;
use App\Models\MashineSet;

class IndexController extends BaseController
{
    public function __invoke(){
        $mashines = Mashine::all();
        $sets = Set::all();
        $mashine_sets = MashineSet::all();
  
        
        return view('order.index', compact('mashines', 'sets', 'mashine_sets'));
        
    }
}
