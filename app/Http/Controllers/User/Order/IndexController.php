<?php

namespace App\Http\Controllers\User\Order;

use App\Http\Requests\Order\FilterRequest;
use Illuminate\Routing\Controller as BaseController;
use App\Models\Mashine;
use App\Models\Set;
use App\Models\MashineSet;
use App\Models\Order;
use Carbon\Carbon;

class IndexController extends BaseController
{
    public function __invoke(FilterRequest $request){

        $data = $request->validated();
        //dd($data);

        $mashines = Mashine::all();
         $orders = Order::all()->where('category_id', '=','1')->where('content', '!=', '');
         $sets = Set::all();
         $mashine_sets = MashineSet::all();
        //нужно передать в коллекцию $mashines дату в формате Carbon
        foreach($mashines as $mashine){
            foreach($mashine->orders as $data){
                $data->carbon = Carbon::parse($data->created_at);
            };
            
        }

        
        
        return view('order.index', compact('mashines', 'sets', 'mashine_sets', 'orders'));//
        
    }
}
