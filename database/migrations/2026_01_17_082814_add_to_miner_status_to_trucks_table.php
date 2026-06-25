<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
        // КОНСТРУКТОР для работы Doctrine DBAL
    public function __construct()
    {
        // Регистрируем enum как string для корректной работы Doctrine DBAL
        DB::connection()->getDoctrineSchemaManager()->getDatabasePlatform()->registerDoctrineTypeMapping('enum', 'string');
    }
    public function up()
    {
        Schema::table('trucks', function (Blueprint $table) {
            $table->enum('status', [
                'free', 'to_miner', 'loading', 'transporting', 'unloading', 'completed',
                'maintenance', 'fueling', 'breakdown'
            ])->default('free')->change();
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trucks', function (Blueprint $table) {
            //
        });
    }
};
