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
        Schema::create('mashine_sets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mashine_id');
            $table->unsignedBigInteger('set_id');
            
            $table->index('mashine_id', 'mashine_set_mashine_idx');
            $table->index('set_id', 'mashine_set_set_idx');

            $table->foreign('mashine_id', 'mashine_set_mashine_fk')->on('mashines')->references('id');
            $table->foreign('set_id', 'mashine_set_set_fk')->on('sets')->references('id');





            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mashine_sets');
    }
};
