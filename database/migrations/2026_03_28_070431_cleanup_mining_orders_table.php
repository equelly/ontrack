<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mining_orders', function (Blueprint $table) {
            // Удаляем поля которые больше не нужны
            $table->dropColumn(['score', 'assigned_round', 'sequence']);
            
            // Делаем необязательные поля nullable
            $table->decimal('distance_km', 8, 2)->nullable()->change();
            $table->unsignedBigInteger('rock_id')->nullable()->change();
            $table->unsignedBigInteger('zone_id')->nullable()->change();
            $table->unsignedBigInteger('operator_id')->nullable()->change();
            $table->unsignedBigInteger('truck_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('mining_orders', function (Blueprint $table) {
            $table->decimal('score', 8, 2)->default(0);
            $table->unsignedInteger('assigned_round')->default(1);
            $table->unsignedInteger('sequence')->default(0);
        });
    }
};
