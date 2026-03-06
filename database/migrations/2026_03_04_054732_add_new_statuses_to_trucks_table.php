<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Получаем текущий enum
        $enumValues = DB::selectOne("SHOW COLUMNS FROM trucks WHERE Field = 'status'");
        
        // Добавляем новые значения
        DB::statement("ALTER TABLE trucks MODIFY COLUMN status ENUM(
            'free',
            'to_miner',
            'loading',
            'transporting',
            'unloading',
            'completed',
            'breakdown',
            'maintenance',
            'fueling',
            'waiting_loading',
            'waiting_unloading',
            'delayed'
        ) DEFAULT 'free'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE trucks MODIFY COLUMN status ENUM(
            'free',
            'to_miner',
            'loading',
            'transporting',
            'unloading',
            'completed',
            'breakdown',
            'maintenance',
            'fueling'
        ) DEFAULT 'free'");
    }
};