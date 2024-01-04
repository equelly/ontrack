<?php

namespace App\Http\Controllers\Admin\Set;



use Illuminate\Routing\Controller as BaseController;
use App\Models\Set;

class StoreController extends BaseController
{
    public function __invoke(){

      $data = request()->validate([
      
        'name'=>'string',

      ]);
      //добавление уникального значения по ключам в массиве первого аргумента метода firstOrCreate()
      //-------------------------
        Set::firstOrCreate ([
        'name'=>$data['name']], $data);

    
      return redirect()->route('admin.order.index');
    }
}
