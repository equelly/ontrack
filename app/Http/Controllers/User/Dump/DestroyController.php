<?php

namespace App\Http\Controllers\User\Dump;

use App\Models\Dump;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;

class DestroyController extends BaseController
{
    public function __invoke(Dump $dump){
        //  
      DB::beginTransaction();  // Начинаем транзакцию

    try {
        // ✅ ШАГ 1: ОБНУЛЯЕМ loader_zone_id в equipment.dumps
        DB::table('equipment.dumps')
            ->where('id', $dump->id)
            ->update(['loader_zone_id' => null]);

        // ✅ ШАГ 2: Удаляем связанные зоны
        $zoneIds = $dump->zones()->pluck('id')->toArray();
        if (!empty($zoneIds)) {
            // Обнуляем все ссылки на эти зоны в equipment
            DB::table('equipment.dumps')
                ->whereIn('loader_zone_id', $zoneIds)
                ->update(['loader_zone_id' => null]);

            // Теперь удаляем зоны
            $dump->zones()->delete();
        }

        // ✅ ШАГ 3: Удаляем основной дамп
        $dump->delete();

        DB::commit();  // Фиксируем изменения

        return redirect()->route('dump.index')
            ->with('success', 'Перегрузка № '. $dump->id. ' удалена со всеми связями!');

    } catch (\Exception $e) {
        DB::rollback();  // Откатываем при ошибке
        return back()->with('error', 'Ошибка удаления: '. $e->getMessage());
    }
  }
}