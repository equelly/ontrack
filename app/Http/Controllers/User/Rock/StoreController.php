<?php

namespace App\Http\Controllers\User\Rock;

use App\Http\Controllers\Controller;
use App\Models\Rock;
use Illuminate\Http\Request;

class StoreController extends Controller
{
    public function __invoke(Request $request)
    {
        $data = $request->validate([
            'name_rock' => 'required|string|max:255|unique:rocks,name_rock',
        ]);

        $rock = Rock::create($data);

        return redirect()->route('rocks.index')
            ->with('success', "Порода '{$rock->name_rock}' создана");
    }
}
