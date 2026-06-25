<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucks', function (Blueprint $table) {
            // Пробег
            if (!Schema::hasColumn('trucks', 'mileage')) {
                $table->integer('mileage')->default(0)->after('fuel_level')->comment('Общий пробег, км');
            }
            if (!Schema::hasColumn('trucks', 'mileage_since_fuel')) {
                $table->integer('mileage_since_fuel')->default(0)->after('mileage')->comment('Пробег с последней заправки, км');
            }

            // Мото-часы (в минутах для точности)
            if (!Schema::hasColumn('trucks', 'moto_minutes')) {
                $table->integer('moto_minutes')->default(0)->after('mileage_since_fuel')->comment('Общие мото-минуты');
            }
            if (!Schema::hasColumn('trucks', 'moto_minutes_since_to')) {
                $table->integer('moto_minutes_since_to')->default(0)->after('moto_minutes')->comment('Мото-минуты с последнего ТО');
            }

            // Последний тип ТО
            if (!Schema::hasColumn('trucks', 'last_to_type')) {
                $table->string('last_to_type', 10)->nullable()->after('moto_minutes_since_to')->comment('Последний тип ТО: TO-1 или TO-2');
            }
        });
    }

    public function down(): void
    {
        Schema::table('trucks', function (Blueprint $table) {
            $columns = ['mileage', 'mileage_since_fuel', 'moto_minutes', 'moto_minutes_since_to', 'last_to_type'];
            foreach ($columns as $col) {
                if (Schema::hasColumn('trucks', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
