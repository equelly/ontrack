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
    Schema::create('miner_dump_distances', function (Blueprint $table) {
        $table->id();

        // ← СВЯЗЬ С ДОБЫТЧИКОМ И ДАМПОМ
        $table->foreignId('miner_id')->constrained('miners')->onDelete('cascade');
        $table->foreignId('dump_id')->constrained('dumps')->onDelete('cascade');

        // ← ГЛАВНОЕ ПОЛЕ: РАССТОЯНИЕ!
        $table->decimal('distance_km', 8, 2);  // ← 50.50 км

        // ← ОПЦИОНАЛЬНО: ВРЕМЯ ПУТИ
        $table->decimal('travel_time_hours', 5, 2)->nullable();  // ← 1.25 часа

        $table->timestamps();

        // ← УНИКАЛЬНОСТЬ: один miner → один dump = одно расстояние
        $table->unique(['miner_id', 'dump_id']);

        // ← ИНДЕКС ДЛЯ БЫСТРОГО ПОИСКА БЛИЖАЙШИХ
        $table->index(['miner_id', 'distance_km']);
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('miner_dump_distances');
    }
};
