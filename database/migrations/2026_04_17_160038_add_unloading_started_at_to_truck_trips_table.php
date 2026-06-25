<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('truck_trips', function (Blueprint $table) {
            $table->datetime('unloading_started_at')->nullable()->after('loaded_at');
        });
    }

    public function down(): void
    {
        Schema::table('truck_trips', function (Blueprint $table) {
            $table->dropColumn('unloading_started_at');
        });
    }
};
