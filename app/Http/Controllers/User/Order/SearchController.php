<?php

namespace App\Http\Controllers\User\Order;

use App\Http\Requests\Order\FilterRequest;
use App\Models\Category;
use Illuminate\Routing\Controller as BaseController;
use App\Models\Mashine;
use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SearchController extends BaseController
{
    public function __invoke(){

      
        
        if(isset($_GET['action']) && $_GET['action'] == 'search' || isset($_GET['page']) ){

            $query = Order::query();
            $user_name = 'невыбрано';
            $mashine_number = 'невыбрано';
            $category_title = 'невыбрано';
            // 
            if(isset($_GET['category_id']) && $_GET['category_id'] != '')  {
               
                $query->where('category_id', $_GET['category_id']);
                $categories = Category::all();
                foreach($categories as $category){
                    if($category->id == $_GET['category_id'])
                    $category_title = $category->title;
                }
              
            }
            if(isset($_GET['users']) && $_GET['users'] != ''){
                $query->where('user_id_req', $_GET['users']);
                $users = User::all();
                foreach($users as $user){
                    if($user->id == $_GET['users'])
                    $user_name = $user->name;
                }
                
            }
            if(isset($_GET['mashine_id']) && $_GET['mashine_id'] != ''){
                $query->where('mashine_id', $_GET['mashine_id']);
                $mashines = Mashine::all();
                foreach($mashines as $mashine){
                    if($mashine->id == $_GET['mashine_id'])
                    $mashine_number = $mashine->number;
                }
                
            }
            if(isset($_GET['created_at']) && $_GET['created_at'] != ''){
                $query->where('created_at',  'like', "%{$_GET['created_at']}%");
            }
            if(isset($_GET['content']) && $_GET['content'] != ''){
                $query->where('content', 'like', "%{$_GET['content']}%");
              }
              //метод paginate() разделит результаты на фрагментированные страницы, а get() выведет все найденные
                $searched_orders = $query->where('content', '!=', '')->orderBy('created_at')->paginate(10);
        //нужно передать в коллекцию $searched_orders дату в формате Carbon
        foreach($searched_orders as $order){
            
               // dd($data);
                $order->createCarbon = Carbon::parse($order->created_at);
                $order->updatedCarbon = Carbon::parse($order->updated_at);
           
        }
       
                $users = User::all();
                $categories = Category::all();
                $mashines = Mashine::all();
            
                  return view('order.searched', compact('searched_orders', 'categories', 'mashines', 'users', 'user_name', 'mashine_number', 'category_title'));
        };

          $users = User::all();
          $categories = Category::all();
          $mashines = Mashine::all();
      
            return view('order.search', compact('categories', 'mashines', 'users'));
       
    }
}
