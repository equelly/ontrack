<?php

namespace App\Http\Controllers\Admin\User;

use App\Models\Mashine;
use Illuminate\Routing\Controller as BaseController;
use App\Models\Order;
use App\Models\Set;
use App\Models\User;

class IndexController extends BaseController
{
    public function __invoke(){

        
      $orders = Order::all();
      $mashines = Mashine::all();
      $cur_orders = Order::all()->where('category_id', '=','1')->where('content', '!=', '');
      $exc_orders = Order::all()->where('category_id', '=','2')->where('content', '!=', '');
      $den_orders = Order::all()->where('category_id', '=','3')->where('content', '!=', '');

        $sets = Set::all();
        $users = User::all();
        
      return view('admin.users.index', compact('sets', 'cur_orders', 'exc_orders', 'den_orders', 'orders', 'mashines', 'users'));  
    }
}
