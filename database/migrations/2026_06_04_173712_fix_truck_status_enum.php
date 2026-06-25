<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucks', function (Blueprint $table) {
            // Используем нативный метод Laravel для изменения ENUM
            $table->enum('status', [
                'free', 'to_miner', 'loading', 'transporting',
                'unloading', 'completed', 'delayed', 'breakdown',
                'maintenance', 'fueling', 'service', 'waiting_loading',
                'waiting_unloading'
            ])->default('free')->change();
        });
    }

    public function down(): void
    {
        Schema::table('trucks', function (Blueprint $table) {
            // Возвращаем прошлый список, если потребуется откат
            $table->enum('status', [
                'free', 'to_miner', 'loading', 'transporting',
                'unloading', 'completed', 'delayed', 'breakdown',
                'maintenance', 'fueling', 'waiting_loading', 'waiting_unloading'
            ])->default('free')->change();
        });
    }
};
