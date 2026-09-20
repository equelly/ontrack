<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Добавить поле distance_km в truck_trips.
 *
 * Раньше расстояние хранилось только в mining_orders.distance_km,
 * и для получения расстояния trip нужно было загружать связь miningOrder.
 * Теперь каждый trip хранит своё расстояние — запросы быстрее и проще.
 *
 * Сохраняется при создании trip в RouteAssignmentService::createTripAndAssign()
 * и BermService::assignTruckToBerm().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('truck_trips', function (Blueprint $table) {
            $table->decimal('distance_km', 8, 2)->default(0)->after('mining_order_id');
        });

        // Заполняем distance_km для существующих trip из mining_orders
        \Illuminate\Support\Facades\DB::statement('
            UPDATE truck_trips
            INNER JOIN mining_orders ON mining_orders.id = truck_trips.mining_order_id
            SET truck_trips.distance_km = COALESCE(mining_orders.distance_km, 0)
            WHERE truck_trips.distance_km = 0
        ');
    }

    public function down(): void
    {
        Schema::table('truck_trips', function (Blueprint $table) {
            $table->dropColumn('distance_km');
        });
    }
};
