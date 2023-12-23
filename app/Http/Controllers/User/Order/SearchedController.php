<?php

namespace App\Http\Controllers\User\Order;

use App\Http\Requests\Order\FilterRequest;
use App\Models\Category;
use Illuminate\Routing\Controller as BaseController;
use App\Models\Mashine;
use App\Models\Set;
use App\Models\MashineSet;
use App\Models\Order;
use App\Models\User;

class SearchedController extends BaseController
{
    public function __invoke(FilterRequest $request){

        $data = $request->validated();
        
        if(isset($_GET['active']) && $_GET['active'] == 'search'){
           dd($data); 
        }
        $users = User::all();
        $categories = Category::all();
        $mashines = Mashine::all();
    
          return view('order.search', compact('categories', 'mashines', 'users'));
       
       
    }
}
