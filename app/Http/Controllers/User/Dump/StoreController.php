<?php

namespace App\Http\Controllers\User\Dump;

use App\Http\Controllers\Controller;
use App\Models\Dump;
use Illuminate\Http\Request;

class StoreController extends Controller
{
    /**
     * Создать новый отвал
     */
    public function __invoke(Request $request)
    {
        $data = $request->validate([
            'name_dump' => 'required|string|max:255',
            'zones' => 'required|array|min:1',
            'zones.*.name_zone' => 'required|string',
            'zones.*.capacity' => 'required|numeric|min:0.1',
            'zones.*.rock_id' => 'required|exists:rocks,id',
        ], [
            'zones.*.capacity.required' => 'Емкость зоны должна быть установлена больше 0',
            'zones.*.capacity.min' => 'Емкость зоны должна быть установлена больше 0',
            'zones.*.capacity.numeric' => 'Емкость зоны должна быть числом',
            'zones.*.rock_id.required' => 'Выберите породу для зоны',
        ]);

        $dump = Dump::create($data);

        if ($request->has('zones')) {
            foreach ($request->zones as $zoneData) {
                $dump->zones()->create([
                    'name_zone' => $zoneData['name_zone'],
                    'volume' => $zoneData['capacity'] ?? null,
                    'delivery' => $zoneData['delivery'] ?? false,
                    'rock_id' => $zoneData['rock_id'],
                ]);
            }
        }

        return redirect()->route('dump.edit', $dump->id)
            ->with('success', 'Отвал успешно создан');
    }
}
