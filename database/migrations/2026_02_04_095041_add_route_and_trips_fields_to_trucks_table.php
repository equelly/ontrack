<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucks', function (Blueprint $table) {
            $table->unsignedInteger('route_version')
                ->default(1)
                ->after('status');

            $table->unsignedInteger('route_ack_version')
                ->nullable()
                ->after('route_version');

            $table->unsignedInteger('trips_count')
                ->default(0)
                ->after('current_load');
        });
    }

    public function down(): void
    {
        Schema::table('trucks', function (Blueprint $table) {
            $table->dropColumn([
                'route_version',
                'route_ack_version',
                'trips_count',
            ]);
        });
    }
};

