<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_posts', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['fueling', 'maintenance', 'tire_service'])->default('fueling');
            $table->string('name')->nullable();
            $table->boolean('is_occupied')->default(false);
            $table->unsignedBigInteger('current_truck_id')->nullable();
            $table->timestamp('occupied_at')->nullable();
            $table->integer('estimated_free_at')->nullable(); // минуты до освобождения
            $table->timestamps();

            $table->foreign('current_truck_id')->references('id')->on('trucks')->onDelete('set null');
            $table->index(['type', 'is_occupied']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_posts');
    }
};
