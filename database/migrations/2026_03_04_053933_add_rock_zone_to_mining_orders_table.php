<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mining_orders', function (Blueprint $table) {
            $table->foreignId('rock_id')->nullable()->after('dump_id')->constrained();
            $table->foreignId('zone_id')->nullable()->after('rock_id')->constrained();
        });
    }

    public function down(): void
    {
        Schema::table('mining_orders', function (Blueprint $table) {
            $table->dropForeign(['rock_id']);
            $table->dropForeign(['zone_id']);
            $table->dropColumn(['rock_id', 'zone_id']);
        });
    }
};