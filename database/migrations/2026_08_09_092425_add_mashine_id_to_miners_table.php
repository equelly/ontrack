<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('miners', function (Blueprint $table) {
            $table->foreignId('mashine_id')->nullable()->constrained('mashines')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('miners', function (Blueprint $table) {
            //
            $table->dropColumn('mashine_id');
        });
    }
};
