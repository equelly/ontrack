<?php

namespace App\Http\Controllers\User\Dump;

use App\Http\Controllers\Controller;
use App\Models\Zone;
use App\Models\Dump;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UpdateController extends Controller
{
    /**
     * Обновить отвал (основные данные)
     */
    public function __invoke(Request $request, Dump $dump)
    {
        $data = $request->validate([
            'name_dump' => 'sometimes|string|max:255',
            'delivered_volume' => 'sometimes|numeric|min:0',
        ]);

        $dump->update($data);

        return redirect()->route('dump.index')
            ->with('success', 'Отвал обновлён');
    }

    /**
     * Обновить зону (породы, приём, вместимость)
     */
    public function zone(Request $request, Zone $zone)
    {
        $data = $request->validate([
            'delivery' => 'sometimes|boolean',
            'rock_ids' => 'sometimes|array',
            'rock_ids.*' => 'exists:rocks,id',
            'capacity' => 'sometimes|numeric|min:0',
        ]);

        DB::transaction(function () use ($zone, $data) {
            if (isset($data['delivery'])) {
                $zone->delivery = $data['delivery'];
            }

            if (isset($data['capacity'])) {
                $zone->capacity = $data['capacity'];
            }

            $zone->save();

            if (isset($data['rock_ids'])) {
                $zone->rocks()->sync($data['rock_ids']);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Зона обновлена',
            'zone' => $zone->fresh(['rocks'])
        ]);
    }
}
