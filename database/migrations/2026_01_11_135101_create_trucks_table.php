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
        Schema::create('trucks', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();        // А123БВ45
            $table->string('brand')->nullable();       // КамАЗ, MAN
            $table->decimal('load_capacity', 8, 2);    // 20.50 тонн
            $table->string('driver_id')->nullable();   // operator_id из users
            $table->enum('status', [
                'free', 'loading', 'transporting', 
                'unloading', 'maintenance'
            ])->default('free');
            $table->decimal('current_load', 8, 2)->default(0);
            $table->timestamp('last_free_at')->nullable();
            $table->timestamps();
            
            $table->index(['status', 'driver_id']);
            $table->index('last_free_at');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trucks');
    }
};
