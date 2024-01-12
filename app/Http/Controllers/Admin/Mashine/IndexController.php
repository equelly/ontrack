<?php

namespace App\Http\Controllers\Admin\Mashine;


use Illuminate\Routing\Controller as BaseController;
use App\Models\Mashine;
use App\Models\Order;
use App\Models\Set;

class IndexController extends BaseController
{
    public function __invoke(){

        
      $orders = Order::all();
      $sets = Set::all();
      $cur_orders = Order::all()->where('category_id', '=','1')->where('content', '!=', '');
      $exc_orders = Order::all()->where('category_id', '=','2')->where('content', '!=', '');
      $den_orders = Order::all()->where('category_id', '=','3')->where('content', '!=', '');

        $mashines = Mashine::all();
        
      return view('admin.mashine.index', compact('mashines', 'cur_orders', 'exc_orders', 'den_orders', 'orders', 'sets'));  
    }
}
