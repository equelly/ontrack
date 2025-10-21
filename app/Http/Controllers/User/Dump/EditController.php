<?php

namespace App\Http\Controllers\User\Dump;


use App\Models\Dump;
use Illuminate\Routing\Controller as BaseController;
use App\Models\Rock;

class EditController extends BaseController
{
    public function __invoke(Dump $dump){
        // 
          
          $rocks = Rock::all();
          
      
            return view('dump.edit', compact('dump', 'rocks'));
    }
}
