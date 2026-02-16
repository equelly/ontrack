<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dumps', function (Blueprint $table) {
            $table->decimal('delivered_volume', 10, 2)
                ->default(0)
                ->after('id');

            $table->unsignedInteger('trips_count')
                ->default(0)
                ->after('delivered_volume');
        });
    }

    public function down(): void
    {
        Schema::table('dumps', function (Blueprint $table) {
            $table->dropColumn([
                'delivered_volume',
                'trips_count',
            ]);
        });
    }
};

