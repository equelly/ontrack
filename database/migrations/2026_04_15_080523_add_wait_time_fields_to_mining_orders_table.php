<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Добавляем поля для отслеживания времени ожидания разгрузки
     * и корректировки веса маршрута при перегрузке зоны
     */
    public function up(): void
    {
        Schema::table('mining_orders', function (Blueprint $table) {
            // Временная корректировка веса при перегрузке зоны
            // Отрицательное значение = уменьшение потока грузовиков
            $table->integer('weight_adjustment')->default(0)->after('weight');

            // Среднее время ожидания разгрузки в секундах
            $table->integer('avg_wait_time')->default(0)->after('weight_adjustment');

            // Суммарное время ожидания разгрузки за период (для статистики)
            $table->integer('total_wait_time')->default(0)->after('avg_wait_time');

            // Время последнего расчёта метрик
            $table->timestamp('metrics_updated_at')->nullable()->after('total_wait_time');
        });
    }

    public function down(): void
    {
        Schema::table('mining_orders', function (Blueprint $table) {
            $table->dropColumn([
                'weight_adjustment',
                'avg_wait_time',
                'total_wait_time',
                'metrics_updated_at',
            ]);
        });
    }
};
