<?php

namespace App\Http\Controllers\Admin\Order;

use App\Http\Requests\Order\UpdateRequest;
use Illuminate\Routing\Controller as BaseController;
use App\Models\MashineSet;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class UpdateController extends BaseController
{
    public function __invoke(UpdateRequest $request, Order $order){

    
 
        $data = $request->validated();
       
     //сохраним массив sets  из полученных данных в отдельном массиве $sets для передачи в модель MashineSet и последующей записи в БД табл.mashine_sets
     if(isset($data['sets'])){
    
      $sets = $data['sets'];
        //a из массива $data удалим
          unset($data['sets']);  
          
             // удалим старые записи из БД 
              DB::table('mashine_sets')->where('mashine_id', '=', $order->mashine->id)->delete(); 
              
    
          foreach($sets as $set){
            MashineSet::firstOrCreate([
            'mashine_id'=>$order->mashine_id,
            'set_id'=>$set,
    
            ]);
          }
        }
      
          $order->update($data);
          
    
          return redirect()->route('admin.order.index');
    }
}
