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
            //
            $table->timestamp('last_assigned_at')->nullable()->after('id');
            $table->integer('wrr_cursor')->default(0);

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mining_orders', function (Blueprint $table) {
            //
            $table->dropColumn('last_assigned_at');
            $table->integer('wrr_cursor')->default(0);

        });
    }
};
