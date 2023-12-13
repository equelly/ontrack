<?php

namespace App\Http\Controllers\Admin\Order;

use Illuminate\Routing\Controller as BaseController;
use App\Models\Order;

class DestroyController extends BaseController
{
    public function __invoke(Order $order){
        //  
        
          $order->delete();
        
          return redirect()->route('admin.order.index');
        }
}
