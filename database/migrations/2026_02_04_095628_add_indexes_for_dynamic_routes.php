<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mining_orders', function (Blueprint $table) {
            $table->index(['truck_id', 'active']);
            $table->index(['truck_id', 'sequence']);
        });

        Schema::table('trucks', function (Blueprint $table) {
            $table->index('driver_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('mining_orders', function (Blueprint $table) {
            $table->dropIndex(['truck_id', 'active']);
            $table->dropIndex(['truck_id', 'sequence']);
        });

        Schema::table('trucks', function (Blueprint $table) {
            $table->dropIndex(['driver_id']);
            $table->dropIndex(['status']);
        });
    }
};

