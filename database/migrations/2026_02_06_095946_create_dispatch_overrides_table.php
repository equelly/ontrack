<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDispatchOverridesTable extends Migration
{
    public function up()
    {
        Schema::create('dispatch_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('truck_id')->constrained('trucks')->cascadeOnDelete();
            $table->foreignId('mining_order_id')->constrained('mining_orders')->cascadeOnDelete();
            $table->enum('type', ['hard','one_time'])->default('one_time');
            $table->boolean('active')->default(true);
            $table->timestamp('used_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('dispatch_overrides');
    }
}

