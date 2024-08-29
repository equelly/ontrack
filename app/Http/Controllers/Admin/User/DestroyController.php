<?php

namespace App\Http\Controllers\Admin\User;


use App\Models\Set;
use App\Models\User;
use Illuminate\Routing\Controller as BaseController;


class DestroyController extends BaseController
{
    public function __invoke(User $user){
        //  
        
          $user->delete();
        
          return redirect()->route('admin.users.index');
        }
}
