<?php

namespace App\Http\Controllers\User\Order;

use App\Models\Category;
use Illuminate\Routing\Controller as BaseController;
use App\Models\Set;
use App\Models\MashineSet;
use App\Models\Order;

class EditController extends BaseController
{
    public function __invoke(Order $order){
        // 
          $sets = Set::all();
          $categories = Category::all();
          $mashine_sets = MashineSet::all();
      
            return view('order.edit', compact('order', 'sets', 'categories', 'mashine_sets'));
    }
}
