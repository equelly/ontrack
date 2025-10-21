<?php

namespace App\Http\Controllers\User\Dump;

use App\Models\Dump;
use Illuminate\Routing\Controller as BaseController;

class ShowController extends BaseController
{
    public function __invoke(Dump $dump){
    
        $dump = Dump::FindOrFail($dump->id);
        
        
     
        return view('dump.show', compact('dump'));
    }
}
