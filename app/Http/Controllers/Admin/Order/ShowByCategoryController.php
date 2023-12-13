<?php

namespace App\Http\Controllers\Admin\Order;

use App\Models\Category;
use Illuminate\Routing\Controller as BaseController;
use App\Models\Mashine;
use App\Models\MashineSet;
use App\Models\Order;
use App\Models\Set;

class ShowByCategoryController extends BaseController
{
    public function __invoke($id){

         $order_cat = Order::where('category_id',$id)->get();
         $category = Category::FindOrFail($id);
         $mashines = Mashine::all();

        $mashines = Mashine::all();
        $orders = Order::all();
        $cur_orders = Order::all()->where('category_id', '=','1')->where('content', '!=', '');
        $exc_orders = Order::all()->where('category_id', '=','2')->where('content', '!=', '');
        $den_orders = Order::all()->where('category_id', '=','3')->where('content', '!=', '');
        $sets = Set::all();
        $mashine_sets = MashineSet::all();
     
        return view('admin.order.showByCategory', compact('mashines', 'sets', 'mashine_sets', 'cur_orders', 'exc_orders', 'den_orders', 'orders', 'order_cat'));
    }

}
