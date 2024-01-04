<?php

namespace App\Http\Controllers\Admin\Order;

use App\Models\Category;
use App\Models\Mashine;
use Illuminate\Routing\Controller as BaseController;
use App\Models\Set;
use App\Models\MashineSet;
use App\Models\Order;

class EditController extends BaseController
{
    public function __invoke(Order $order){
        //
          $mashines = Mashine::all(); 
          $sets = Set::all();
          $categories = Category::all();
          $mashine_sets = MashineSet::all();
          $orders = Order::all();
          $cur_orders = Order::all()->where('category_id', '=','1')->where('content', '!=', '');
          $exc_orders = Order::all()->where('category_id', '=','2')->where('content', '!=', '');
          $den_orders = Order::all()->where('category_id', '=','3')->where('content', '!=', '');
      
            return view('admin.order.edit', compact('order', 'sets', 'categories', 'mashine_sets', 'cur_orders', 'exc_orders', 'den_orders', 'orders', 'mashines'));
    }
}
