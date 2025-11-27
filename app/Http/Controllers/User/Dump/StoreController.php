<?php

namespace App\Http\Controllers\User\Dump;



use App\Models\Dump;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Http\Request;


class StoreController extends BaseController
{
    public function __invoke(Request $request){
        $validated = $request->validate([
            'name_dump' => 'required|string|max:255',
            

        ]);
        Dump::firstOrCreate($validated);

        return redirect()->route('dump.index')
            ->with('success', "Перегрузочный пункт № '{$validated['name_dump']}' добавлен!Добавьте небходимые зоны для работы в системе распределения");

     
    }
}
