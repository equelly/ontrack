<?php

namespace App\Http\Controllers\User\Order;

use Illuminate\Routing\Controller as BaseController;
use App\Models\Mashine;
use App\Models\Order;

class ShowController extends BaseController
{
    public function __invoke(Order $order){
    
        $order = Order::FindOrFail($order->id);
        $mashine_sets = Mashine::find($order->mashine_id);
        
     
        return view('order.show', compact('order', 'mashine_sets'));
    }
}
