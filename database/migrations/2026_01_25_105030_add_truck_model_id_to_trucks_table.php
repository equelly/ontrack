<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucks', function (Blueprint $table) {
            $table->foreignId('truck_model_id')->nullable()->constrained()->after('id');
            $table->decimal('fuel_level', 8, 2)->default(100.00)->after('truck_model_id');
            $table->timestamp('last_fuel_update')->nullable()->after('fuel_level');
        });
    }

    public function down(): void
    {
        Schema::table('trucks', function (Blueprint $table) {
            $table->dropForeign(['truck_model_id']);
            $table->dropColumn(['truck_model_id', 'fuel_level', 'last_fuel_update']);
        });
    }
};
