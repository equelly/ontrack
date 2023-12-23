<?php

namespace App\Http\Controllers\Admin\Mashine;

use App\Models\Mashine;
use Illuminate\Routing\Controller as BaseController;
use App\Models\Order;

class DestroyController extends BaseController
{
    public function __invoke(Mashine $mashine){
        //  
        
          $mashine->delete();
        
          return redirect()->route('admin.mashine.index');
        }
}
