<?php
// database/migrations/2026_01_03_xxxxxx_create_mining_orders_table_final.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('mining_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('miner_id');
            $table->unsignedBigInteger('dump_id');
            $table->unsignedBigInteger('operator_id')->nullable();
            $table->decimal('distance_km', 8, 2);
            $table->decimal('score', 8, 2);
            $table->boolean('active')->default(true);
            $table->integer('assigned_round')->default(1);
            $table->timestamps();
            
            // Foreign keys ОТДЕЛЬНО (без constrained)
            $table->foreign('miner_id')->references('id')->on('miners')->onDelete('cascade');
            $table->foreign('dump_id')->references('id')->on('dumps')->onDelete('cascade');
            $table->foreign('operator_id')->references('id')->on('users')->onDelete('set null');
            
            $table->index(['miner_id', 'dump_id']);
            $table->index(['active', 'created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('mining_orders');
    }
};
