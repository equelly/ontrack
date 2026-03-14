<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Привязка оператора экскаватора к экскаватору (miner)
            $table->foreignId('miner_id')
                ->nullable()
                ->after('role')
                ->constrained('miners')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['miner_id']);
            $table->dropColumn('miner_id');
        });
    }
};