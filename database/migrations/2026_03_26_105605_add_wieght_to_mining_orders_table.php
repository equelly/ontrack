<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Добавляем поле weight для пропорции распределения между маршрутами
     * weight = 100 означает базовый вес, 50 = в 2 раза реже, 200 = в 2 раза чаще
     */
    public function up(): void
    {
        Schema::table('mining_orders', function (Blueprint $table) {
            $table->integer('weight')->default(100)->after('active');
        });
    }

    public function down(): void
    {
        Schema::table('mining_orders', function (Blueprint $table) {
            $table->dropColumn('weight');
        });
    }
};
