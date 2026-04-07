<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('miner_pauses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('miner_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // breakdown, maintenance, dismantling, access_setup
            $table->text('reason')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->integer('duration_seconds')->default(0);
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('ended_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['miner_id', 'ended_at']);
            $table->index(['type', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('miner_pauses');
    }
};
