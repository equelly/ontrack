<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('miners', function (Blueprint $table) {
            //
             $table->decimal('capacity_per_trip', 10, 2)->default(50.0)  // 50 m3 за рейс (стандарт)
                  ->after('name_miner');

        $table->boolean('active')->default(true)                     // Работает/не работает
                  ->after('capacity_per_trip');

        $table->text('description')->nullable()                     // Описание (опционально)
                  ->after('active');

        // ИНДЕКС ДЛЯ ФИЛЬТРА АКТИВНЫХ
        $table->index('active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('miners', function (Blueprint $table) {
            //
        });
    }
};
