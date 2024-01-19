<?php

namespace App\Http\Controllers\User\Order;


use App\Http\Requests\Order\StoreRequest;
use Illuminate\Routing\Controller as BaseController;
use App\Models\Mashine;
use App\Models\Set;
use App\Models\MashineSet;
use App\Models\Order;
use Illuminate\Support\Facades\Storage;

class StoreController extends BaseController
{
    public function __invoke(StoreRequest $request){
     
        $data = $request->validated();
      
           
        //сохраним массив sets  из полученных данных в отдельном массиве $sets для передачи в модель MashineSet и последующей записи в БД табл.mashine_sets
        if(!isset($data['sets']) and $data['content']== ''){
          $sets = Set::all();
          $mashines = Mashine::all();
            $error = 'Заполните поле заявки или выберите необходимый пункт в комплектации';
            return view('order.create', compact('error', 'sets', 'mashines'));
            
        }elseif(isset($data['sets'])){
          $sets = $data['sets'];
            //a из массива $data удалим
              unset($data['sets']);
              
              foreach($sets as $set){
                MashineSet::firstOrCreate([
                'mashine_id'=>$data['mashine_id'],
                'set_id'=>$set
                ]);
              }
        
        }
      //добавление уникального значения по ключам в массиве первого аргумента метода firstOrCreate()
      //-------------------------
      // $order = Order::firstOrCreate ([
      //   'content'=>$data['content']], $data);
     //-------------------------
     //добавляем изображение (file) в директорию storage/app/public
      if(isset($data['image']) && $data['image']!== NULL){
        //класс Storage метод put добавит изображение (file) в директорию storage/app/<первый аргумент функции>
      $saveImage = Storage::put('public', $data['image']);
      //разделим строку по символу "/" и сохраним в БД путь для вывода изображения
      $pieces = explode("/", $saveImage);
      
      $data['image'] = $pieces[1];
    }
      //добавление без провереки на уникальность
      Order::create($data);
    
      return redirect()->route('order.index');
    }
}
