<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('mining_orders', function (Blueprint $table) {
            $table->foreignId('truck_id')
                  ->nullable()
                  ->constrained('trucks')
                  ->onDelete('set null')
                  ->after('operator_id');
        });
    }

    public function down()
    {
        Schema::table('mining_orders', function (Blueprint $table) {
            $table->dropForeign(['truck_id']);
            $table->dropColumn('truck_id');
        });
    }
};
