<?php

namespace App\Http\Controllers\Admin\Order;

use Illuminate\Routing\Controller as BaseController;
use App\Models\Mashine;
use App\Models\Order;
use App\Models\Set;

class CreateController extends BaseController
{
    public function __invoke(){

        $sets = Set::all();
        $mashines = Mashine::all();
        $orders = Order::all();
        $cur_orders = Order::all()->where('category_id', '=','1')->where('content', '!=', '');
        $exc_orders = Order::all()->where('category_id', '=','2')->where('content', '!=', '');
        $den_orders = Order::all()->where('category_id', '=','3')->where('content', '!=', '');
          return view('admin.order.create', compact('sets', 'mashines', 'cur_orders', 'exc_orders', 'den_orders', 'orders'));
        
    }
}
