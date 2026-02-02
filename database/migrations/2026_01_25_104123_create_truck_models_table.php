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
        Schema::create('truck_models', function (Blueprint $table) {
            $table->id();
            $table->string('brand');           // BelAZ, KamAZ, Volvo
            $table->string('model');           // 75710, 6580, FH16
            $table->string('full_name');       // BelAZ-75710
            $table->decimal('fuel_capacity', 8, 2)->default(500.00);  // л
            $table->decimal('fuel_consumption', 6, 2)->default(35.00); // л/100км
            $table->decimal('load_capacity', 8, 2)->default(40.00);   // т
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('truck_models');
    }
};
