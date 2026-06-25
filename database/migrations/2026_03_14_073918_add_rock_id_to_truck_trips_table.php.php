<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('truck_trips', function (Blueprint $table) {
            $table->unsignedBigInteger('rock_id')->nullable()->after('load_volume');
            $table->foreign('rock_id')->references('id')->on('rocks')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('truck_trips', function (Blueprint $table) {
            $table->dropForeign(['rock_id']);
            $table->dropColumn('rock_id');
        });
    }
};