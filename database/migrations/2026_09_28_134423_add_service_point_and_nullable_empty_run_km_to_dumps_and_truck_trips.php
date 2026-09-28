<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 1) Добавить флаг is_service_point в dumps — сервисная точка (отстой/обслуживание/заправка).
 *    Используется как fallback в getCurrentLocationDumpId() когда у самосвала
 *    нет истории рейсов (новый, после долгого простоя, после обслуживания).
 *    Сервисная точка — это обычный Dump, к которому привязаны расстояния до забоев
 *    в miner_dump_distances. Так мы переиспользуем существующую инфраструктуру.
 *
 * 2) Сделать empty_run_km nullable в truck_trips — отличать "нет данных" от "реально 0".
 *    Null = нет данных (самосвал в отстое на момент назначения, или нет записи в distances).
 *    Число = реальное расстояние холостого пробега.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1) Флаг сервисной точки в dumps
        Schema::table('dumps', function (Blueprint $table) {
            $table->boolean('is_service_point')->default(false)->after('name_dump');
            $table->index('is_service_point');
        });

        // 2) empty_run_km в truck_trips — nullable
        // Старые записи сохранят 0 (default), новые могут быть null
        Schema::table('truck_trips', function (Blueprint $table) {
            $table->decimal('empty_run_km', 8, 2)->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('dumps', function (Blueprint $table) {
            $table->dropIndex(['is_service_point']);
            $table->dropColumn('is_service_point');
        });

        Schema::table('truck_trips', function (Blueprint $table) {
            $table->decimal('empty_run_km', 8, 2)->default(0)->change();
        });
    }
};