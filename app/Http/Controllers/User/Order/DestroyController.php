<?php

namespace App\Http\Controllers\User\Order;

use Illuminate\Routing\Controller as BaseController;
use App\Models\Order;

class DestroyController extends BaseController
{
    public function __invoke(Order $order){
        //  
        
          $order->delete();
        
          return redirect()->route('order.index');
        }
}
