<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
        public function up()
    {
        Schema::table('trucks', function (Blueprint $table) {
            $table->enum('status', [
                'free', 'to_miner', 'loading', 'transporting', 'unloading', 'completed',
                'maintenance', 'fueling', 'breakdown', 'in_service'  // ← НОВЫЙ!
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
