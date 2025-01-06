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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->text('content')->nullable();
            $table->text('image')->nullable();
            
            
            $table->timestamps();

            $table->softDeletes();

            $table->unsignedBigInteger('category_id')->default(1);

            $table->index('category_id', 'order_category_idx');
            $table->foreign('category_id', 'order_category_fk')->on('categories')->references('id');

          
            $table->foreignId('mashine_id')->constrained(
                table: 'mashines', indexName: 'orders_mashine_id');

            $table->foreignId('user_id_req')->constrained(
                table: 'users', indexName: 'users_mashine_id')->onDelete('cascade');
            
         
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
