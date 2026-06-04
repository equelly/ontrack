<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Исправляем ENUM статусов грузовика - добавляем все необходимые статусы
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE trucks MODIFY COLUMN status ENUM(
            'free',
            'to_miner',
            'loading',
            'transporting',
            'unloading',
            'completed',
            'delayed',
            'breakdown',
            'maintenance',
            'fueling',
            'service',
            'waiting_loading',
            'waiting_unloading'
        ) DEFAULT 'free'");
    }

    public function down(): void
    {
        // Возврат к базовым статусам
        DB::statement("ALTER TABLE trucks MODIFY COLUMN status ENUM(
            'free',
            'to_miner',
            'loading',
            'transporting',
            'unloading',
            'completed',
            'delayed',
            'breakdown'
        ) DEFAULT 'free'");
    }
};
