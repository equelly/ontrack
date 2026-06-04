<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Добавляем новые статусы для обслуживания
        DB::statement("ALTER TABLE trucks MODIFY COLUMN status ENUM(
            'free',
            'assigned',
            'loading',
            'loaded',
            'transporting',
            'unloading',
            'completed',
            'delayed',
            'breakdown',
            'fueling',
            'maintenance',
            'service'
        ) DEFAULT 'free'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE trucks MODIFY COLUMN status ENUM(
            'free',
            'assigned',
            'loading',
            'loaded',
            'transporting',
            'unloading',
            'completed',
            'delayed',
            'breakdown'
        ) DEFAULT 'free'");
    }
};
