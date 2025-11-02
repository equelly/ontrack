<?php

namespace App\Http\Controllers\User\Dump;

use App\Http\Requests\Dump\UpdateRequest;
use App\Models\Dump;
use App\Models\Zone;
use Illuminate\Routing\Controller as BaseController;


class UpdateController extends BaseController
{
    public function __invoke(UpdateRequest $request, Dump $dump){

      

    $dump->update(
      ['name_dump' => $request->name_dump,
      'loader_zone_id' => $request->loader_zone_id,
    ]);

    // Обновляем зоны
    if ($request->has('zones')) {
        foreach ($request->zones as $zoneData) {
            // Создаём/находим зону
            $zone = Zone::updateOrCreate(
                ['id' => $zoneData['id']?? null],
                [
                    'dump_id' => $dump->id,  // ← Автоматически!
                    'name_zone' => $zoneData['name_zone'],
                    'volume' => $zoneData['volume'],
                    'ship' => $request->boolean('zones.*.ship'),
                    'delivery' => $zoneData['delivery']?? 0,
                ]
            );

            // Rocks (sync) — теперь $zone существует!
            if (isset($zoneData['rocks'])) {
                $rockIds = collect($zoneData['rocks'])->pluck('id')->filter();
                $zone->rocks()->sync($rockIds);
            }
        }
    }

    return redirect()->route('dump.show', $dump)
                    ->with([
                    'success' => true,
                    'message' => $dump->name_dump. ' обновлёны!',
                    'type' => 'success'
                ]);

  }
}