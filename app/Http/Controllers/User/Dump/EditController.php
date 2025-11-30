<?php

namespace App\Http\Controllers\User\Dump;


use App\Models\Dump;
use Illuminate\Routing\Controller as BaseController;
use App\Models\Rock;
use Illuminate\Http\Request;

class EditController extends BaseController
{
    public function __invoke(Request $request, Dump $dump){
        // 
    // ✅ СОХРАНЯЕМ  в session куда нужно будет вернуться после update
    if ($request->has('return_to')) {
        session(['dump_return_to' => $request->get('return_to')]);
    } elseif (str_contains($request->headers->get('referer', ''), 'dumps')) {
        // Если нет параметра, но referer содержит dumps — тоже на index
        session(['dump_return_to' => 'index']);
    } else {
        // По умолчанию на distribution
        session(['dump_return_to' => 'distribution']);
    } 
        $dump->load(['zones:id,dump_id,name_zone,volume,ship,delivery', 'zones.rocks']);
        $allRocks = Rock::select('id', 'name_rock')->get();
        // ФИЛЬТРУЕМ: только зоны с ship = true (доступны для отгрузки)
        $zones = $dump->zones->where('ship', true);

        return view('dump.edit', compact('dump', 'allRocks'));
    }
}
