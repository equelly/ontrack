<?php

namespace App\Http\Controllers\User\Dumps;

use App\Http\Requests\Dump\UpdateRequest;
use App\Models\Dump;
use App\Models\Zone;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class UpdateController extends BaseController
{
    public function __invoke(Request $request, Dump $dump)
{
    // ✅ ВАЛИДАЦИЯ В КОНТРОЛЛЕРЕ
   
    $request->validate([
        'name_dump' => 'required|string|max:255',
        'loader_zone_id' => 'nullable|integer',
    ], [
        'name_dump.required' => 'Название дампа обязательно!',
    ]);

    // ✅ УДАЛЕНИЕ ПОМЕЧЕННЫХ ЗОН
    $deletedZones = 0;
    if ($request->has('delete_zones')) {
        $deleteZoneIds = $request->input('delete_zones', []);
        $deletedZones = $dump->zones()->whereIn('id', $deleteZoneIds)->delete();

    }


    $validated = $request->all();

    // ✅ ВАЛИДАЦИЯ НОВЫХ ЗОН В КОНТРОЛЛЕРЕ
    $newZonesCreated = 0;
   
    if (isset($validated['zones'])) {
        foreach ($validated['zones'] as $index => $zoneData) {

                   // ✅ ПРОВЕРКА: СУЩЕСТВУЮЩАЯ ЗОНА (ИМЕЕТ ID)
        if (isset($zoneData['id']) &&!empty($zoneData['id']) && $zoneData['id']!= 'null') {
                    // ✅ Валидация name_zone (required)
        if (empty($zoneData['name_zone'])) {
            return back()->withErrors(['zones' => 'Название зоны обязательно!']);
        }

        // ✅ Валидация volume 
        if (!isset($zoneData['volume'])) {
            return back()->withErrors(['zones' => 'Объем зоны не указан!']);
        }
        if ($zoneData['volume'] === '' || $zoneData['volume'] === null) {
            return back()->withErrors(['zones' => 'Объем зоны не может быть пустым!']);
        }
        if (!is_numeric($zoneData['volume'])) {
            return back()->withErrors(['zones' => 'Объем зоны должен быть числом!']);
        }
        if ((float)$zoneData['volume'] < 0) {
            return back()->withErrors(['zones' => 'Объем зоны не может быть отрицательным!']);
        }

        // ✅  delivery
        if (isset($zoneData['delivery']) &&!in_array($zoneData['delivery'], [0, 1, '0', '1'])) {
            return back()->withErrors(['zones' => 'Завозка должен быть в формате да/нет!']);
        }
            // ✅ ОБНОВЛЯЕМ СУЩЕСТВУЮЩУЮ ЗОНУ
            $zone = $dump->zones()->find($zoneData['id']);
            if ($zone) {
                $zone->update([
                    'name_zone' => $zoneData['name_zone'],
                    'volume' => (float)$zoneData['volume'],
                    'delivery' => isset($zoneData['delivery'])? 1: 0,
                    'ship' => isset($zoneData['loader_zone_id'])? 1: 0,
                ]);

                // ✅ ОБНОВЛЯЕМ ПОРОДЫ
                $rocks = $zoneData['rocks']?? [];
                $zone->rocks()->sync($rocks);

                }
        }
        // ✅ КОД ДЛЯ НОВЫХ ЗОН 
        elseif (strpos($index, 'new_') === 0) {
                // Проверяем обязательные поля
                if (empty($zoneData['name_zone'])) {
                    return back()->withErrors(['zones' => 'Название зоны обязательно!']);
                }
                // ✅ ШАГ 1: ПРОВЕРКА НА СУЩЕСТВОВАНИЕ
                if (!isset($zoneData['volume'])) {
                    return back()->withErrors(['zones' => 'Объем зоны не указан!']);
                }

                // ✅ ШАГ 2: ПРОВЕРКА НА ПУСТОТУ
                if ($zoneData['volume'] === '' || $zoneData['volume'] === null) {
                    return back()->withErrors(['zones' => 'Объем зоны не может быть пустым!']);
                }

                // ✅ ШАГ 3: ПРОВЕРКА НА ЧИСЛО
                if (!is_numeric($zoneData['volume'])) {
                    return back()->withErrors(['zones' => 'Объем зоны должен быть числом!']);
                }

                // ✅ ШАГ 4: ПРОВЕРКА НА ≥ 0
                if ((float)$zoneData['volume'] < 0) {
                    return back()->withErrors(['zones' => 'Объем зоны не может быть отрицательным!']);
                }


                // Создаем зону
                $newZone = $dump->zones()->create([
                    'name_zone' => $zoneData['name_zone'],
                    'volume' => $zoneData['volume'],
                    'delivery' => isset($zoneData['delivery'])? 1: 0,
                    'ship' => isset($zoneData['loader_zone_id'])? 1: 0,
                ]);

                // Породы
                $rocks = [];
                if (isset($zoneData['rocks']) && is_array($zoneData['rocks'])) {
                    foreach ($zoneData['rocks'] as $rockId) {
                        if ($rockId) {
                            $rocks[] = $rockId;
                        }
                    }
                }
                $newZone->rocks()->attach($rocks);

                $newZonesCreated++;
            }
        }
    }

    // Обновляем дамп
    $dump->update([
        'name_dump' => $validated['name_dump']?? $dump->name_dump,
        'loader_zone_id' => $validated['loader_zone_id']?? $dump->loader_zone_id,
    ]);

    $message = "Информация по перегрузочному пункту №{$dump->name_dump} обновлена! ";
    
    if ($newZonesCreated > 0) $message.= "➕ Добавлено зон: {$newZonesCreated} . ";
    if ($deletedZones > 0) $message.= "🗑️ Удалено: {$deletedZones} зону(ы). ";
 
    // ✅ ЧИТАЕМ ИЗ SESSION на какую страницу перейти после сохранения
    $returnTo = session('dump_return_to', 'distribution'); // по умолчанию distribution

    switch ($returnTo) {
        case 'index':
            return redirect()->route('dump.index')
                ->with('success', $message);

        case 'distribution':
            return redirect()->route('distribution.index')
                ->with('success', $message);

        default:
            return redirect()->back()
                ->with('success', $message);
    }

    return redirect()->back()
        ->with('success', $message);
}


}