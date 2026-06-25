<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('miner_rock', function (Blueprint $table) {
            $table->foreignId('miner_id')->constrained()->onDelete('cascade');
            $table->foreignId('rock_id')->constrained()->onDelete('cascade');
            $table->primary(['miner_id', 'rock_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('miner_rock');
    }
};