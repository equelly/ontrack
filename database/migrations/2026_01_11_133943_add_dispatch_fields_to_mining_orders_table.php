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
        Schema::table('mining_orders', function (Blueprint $table) {
            // Связь с грузовиком (после operator_id)
            $table->foreignId('truck_id')
                  ->nullable()
                  ->constrained('trucks')
                  ->after('operator_id')
                  ->onDelete('set null');
            
            // Статус выполнения (после truck_id)
            $table->enum('status', [
                'pending', 'loading', 'in_transit', 
                'unloading', 'completed', 'cancelled'
            ])->default('pending')
              ->after('truck_id');
            
            // Приоритет (после status)
            $table->unsignedInteger('priority')
                  ->default(0)
                  ->after('status');
            
            // Время завершения (после priority)
            $table->timestamp('completed_at')
                  ->nullable()
                  ->after('priority');
            
            // Индексы для быстрых запросов
            $table->index(['truck_id']);
            $table->index(['status']);
            $table->index(['priority']);
            $table->index(['active', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mining_orders', function (Blueprint $table) {
            $table->dropForeign(['truck_id']);
            $table->dropColumn([
                'truck_id', 
                'status', 
                'priority', 
                'completed_at'
            ]);
        });
    }
};
