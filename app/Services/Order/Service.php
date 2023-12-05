<?php


namespace App\Service\Order;

use App\Models\Mashine;
use App\Models\MashineSet;
use App\Models\Order;
use App\Models\Set;



class Service
{
    public function store($data){
   
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
                //добавление без провереки на уникальность
                Order::create($data);

    }



    public function update(){
        
    }

}