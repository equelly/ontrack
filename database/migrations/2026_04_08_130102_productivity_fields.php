<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Добавляем целевое время погрузки к экскаваторам
        Schema::table('miners', function (Blueprint $table) {
            $table->integer('target_load_time')->nullable()->after('current_rock_id')
                ->comment('Целевое время погрузки в минутах');
        });

        // Добавляем поля времени ожидания и начала погрузки к рейсам
        Schema::table('truck_trips', function (Blueprint $table) {
            $table->timestamp('wait_start')->nullable()->after('started_at')
                ->comment('Время начала ожидания погрузки');
            $table->timestamp('load_start')->nullable()->after('wait_start')
                ->comment('Время начала погрузки');
        });
    }

    public function down(): void
    {
        Schema::table('miners', function (Blueprint $table) {
            $table->dropColumn('target_load_time');
        });

        Schema::table('truck_trips', function (Blueprint $table) {
            $table->dropColumn(['wait_start', 'load_start']);
        });
    }
};
