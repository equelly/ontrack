<?php

namespace App\Http\Controllers\User\Dump;

use App\Http\Requests\Order\FilterRequest;
use App\Models\Dump;
use Illuminate\Routing\Controller as BaseController;
use App\Models\Mashine;
use App\Models\Set;
use App\Models\MashineSet;
use App\Models\Order;
use Carbon\Carbon;

class IndexController extends BaseController
{
    public function __invoke(){

        $mashines = Mashine::all();
        $dumps = Dump::all();
        
        return view('dump.index', compact('mashines', 'dumps'));//
        
    }
}
