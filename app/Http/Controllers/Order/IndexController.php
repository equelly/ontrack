<?php

namespace App\Http\Controllers\Order;

use App\Http\Requests\Order\FilterRequest;
use Illuminate\Routing\Controller as BaseController;
use App\Models\Mashine;
use App\Models\Set;
use App\Models\MashineSet;
use App\Models\Order;

class IndexController extends BaseController
{
    public function __invoke(FilterRequest $request){

        $data = $request->validated();
        //dd($data);

        $mashines = Mashine::all();
        $orders = Order::all()->where('category_id', '=','1')->where('content', '!=', '');
        $sets = Set::all();
        $mashine_sets = MashineSet::all();
  
        
        return view('order.index', compact('mashines', 'sets', 'mashine_sets', 'orders'));
        
    }
}
