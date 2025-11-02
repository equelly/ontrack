<?php

namespace App\Http\Controllers\User\Dump;


use App\Models\Dump;
use Illuminate\Routing\Controller as BaseController;
use App\Models\Rock;

class EditController extends BaseController
{
    public function __invoke(Dump $dump){
        // 
          
        $dump->load(['zones:id,dump_id,name_zone,volume,ship,delivery', 'zones.rocks']);
        $allRocks = Rock::select('id', 'name_rock')->get();
        // ФИЛЬТРУЕМ: только зоны с ship = true (доступны для отгрузки)
        $zones = $dump->zones->where('ship', true);

        return view('dump.edit', compact('dump', 'allRocks'));
    }
}
