<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Mashine;
use App\Models\MashineSet;
use App\Models\Order;
use App\Models\Set;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class OrderController extends BaseController
{
    //use AuthorizesRequests, ValidatesRequests;

    public function index(){
      $mashines = Mashine::all();
      $sets = Set::all();
      $mashine_sets = MashineSet::all();

      
      return view('order.index', compact('mashines', 'sets', 'mashine_sets'));
      
    }

    public function create(){

      $sets = Set::all();
      $mashines = Mashine::all();
        return view('order.create', compact('sets', 'mashines'));
    }

    public function store(){

      $data = request()->validate([
        
        'content'=>'',
        'image'=>'',
        'mashine_id'=>'string',
        'category_id'=>'',
        'user_id_req'=>'string',
        'sets'=>'array',
      ]);
       
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
    
      return redirect()->route('order.index');
  }

//
  public function show(Order $order){
    
    $order = Order::FindOrFail($order->id);
    $mashine_sets = Mashine::find($order->mashine_id);
    
 
    return view('order.show', compact('order', 'mashine_sets'));
}


    public function edit(Order $order){
  // 
    $sets = Set::all();
    $categories = Category::all();
    $mashine_sets = MashineSet::all();

      return view('order.edit', compact('order', 'sets', 'categories', 'mashine_sets'));
    
  }
  public function update(Order $order){

    
 
    $data = request()->validate([
      'content'=>'string',
      'image'=>'',
      'mashine_id'=>'',
      'category_id'=>'',
      'user_id_req'=>'string',
      'sets'=>'array',
    ]);
   dd($data);
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
      

      return redirect()->route('order.index');

  }
  public function destroy(Order $order){
    //  
    
      $order->delete();
    
      return redirect()->route('order.index');
    }
}
