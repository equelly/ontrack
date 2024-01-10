<?php

namespace App\Http\Controllers\Admin\Order;

use Illuminate\Routing\Controller as BaseController;
use App\Models\Mashine;
use App\Models\Order;
use App\Models\Set;

class ShowController extends BaseController
{
    public function __invoke(Order $order){
    
        $order = Order::FindOrFail($order->id);
        $mashine_sets = Mashine::find($order->mashine_id);
        $orders = Order::all();
        $mashines = Mashine::all();
        $sets = Set::all();
        $cur_orders = Order::all()->where('category_id', '=','1')->where('content', '!=', '');
        $exc_orders = Order::all()->where('category_id', '=','2')->where('content', '!=', '');
        $den_orders = Order::all()->where('category_id', '=','3')->where('content', '!=', '');
     
        return view('admin.order.show', compact('order', 'mashine_sets','cur_orders', 'exc_orders', 'den_orders', 'orders', 'mashines', 'sets'));
    }
}
