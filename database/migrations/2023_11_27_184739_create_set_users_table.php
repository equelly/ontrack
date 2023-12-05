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
        Schema::create('set_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('set_id');
            $table->unsignedBigInteger('user_id');
            
            $table->index('set_id', 'set_user_set_idx');
            $table->index('user_id', 'set_user_user_idx');

            $table->foreign('set_id', 'set_user_set_fk')->on('sets')->references('id');
            $table->foreign('user_id', 'set_user_user_fk')->on('users')->references('id');


            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('set_users');
    }
};
