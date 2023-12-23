<?php

namespace App\Http\Controllers\Admin\Mashine;



use Illuminate\Routing\Controller as BaseController;
use App\Models\Mashine;



class StoreController extends BaseController
{
    public function __invoke(){

      $data = request()->validate([
      
        'number'=>'string',

      ]);
      //добавление уникального значения по ключам в массиве первого аргумента метода firstOrCreate()
      //-------------------------
        Mashine::firstOrCreate ([
        'number'=>$data['number']], $data);

    
      return redirect()->route('admin.order.index');
    }
}
