<?php

namespace App\Http\Controllers\Admin\Set;


use App\Models\Set;
use Illuminate\Routing\Controller as BaseController;


class DestroyController extends BaseController
{
    public function __invoke(Set $set){
        //  
        
          $set->delete();
        
          return redirect()->route('admin.set.index');
        }
}
