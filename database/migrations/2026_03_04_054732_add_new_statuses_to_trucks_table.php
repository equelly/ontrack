<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucks', function (Blueprint $table) {
            // Универсальный метод Laravel для изменения колонки
            $table->enum('status', [
                'free', 'to_miner', 'loading', 'transporting',
                'unloading', 'completed', 'breakdown', 'maintenance',
                'fueling', 'waiting_loading', 'waiting_unloading', 'delayed'
            ])->default('free')->change();
        });
    }

    public function down(): void
    {
        Schema::table('trucks', function (Blueprint $table) {
            // Возвращаем старый список статусов
            $table->enum('status', [
                'free', 'to_miner', 'loading', 'transporting',
                'unloading', 'completed', 'breakdown', 'maintenance',
                'fueling'
            ])->default('free')->change();
        });
    }
};
