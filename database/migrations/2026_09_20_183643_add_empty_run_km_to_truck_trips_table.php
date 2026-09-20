<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Добавить поле empty_run_km в truck_trips.
 *
 * empty_run_km — холостой пробег самосвала от предыдущего места разгрузки
 * до забоя текущего рейса. Сохраняется при создании trip в RouteAssignmentService.
 *
 * Используется в:
 *   - ShiftStatistics — суммарный холостой пробег по парку за смену
 *   - Аналитика Панели Диспетчера — эффективность использования самосвалов
 *   - Отчёты — сравнение гружёного и холостого пробега
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('truck_trips', function (Blueprint $table) {
            $table->decimal('empty_run_km', 8, 2)->default(0)->after('distance_km');
        });
    }

    public function down(): void
    {
        Schema::table('truck_trips', function (Blueprint $table) {
            $table->dropColumn('empty_run_km');
        });
    }
};
