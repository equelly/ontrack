<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mining_orders', function (Blueprint $table) {
            $table->unsignedInteger('sequence')
                ->default(0)
                ->after('assigned_round');
        });
    }

    public function down(): void
    {
        Schema::table('mining_orders', function (Blueprint $table) {
            $table->dropColumn('sequence');
        });
    }
};

