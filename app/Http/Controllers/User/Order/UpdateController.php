<?php

namespace App\Http\Controllers\User\Order;

use App\Http\Requests\Order\UpdateRequest;
use Illuminate\Routing\Controller as BaseController;
use App\Models\MashineSet;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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
             //обновляем изображение (file) в директорию storage/app/public
      if(isset($data['image']) && $data['image']!== NULL){
        //класс Storage метод put добавит изображение (file) в директорию storage/app/<первый аргумент функции>
      $saveImage = Storage::put('public', $data['image']);
      //разделим строку по символу "/" и сохраним в БД путь для вывода изображения
      $pieces = explode("/", $saveImage);
      
      $data['image'] = $pieces[1];
    }
      
          $order->update($data);
          
    
          return redirect()->route('order.index');
    }
}
