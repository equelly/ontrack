<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('truck_trips', function (Blueprint $table) {
            $table->id();

            $table->foreignId('truck_id')->constrained();
            $table->foreignId('driver_id')->nullable();
            $table->foreignId('miner_id')->nullable();
            $table->foreignId('dump_id')->nullable();
            $table->foreignId('mining_order_id')->nullable();

            $table->decimal('load_volume', 10, 2)->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();


            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('truck_trips');
    }
};

