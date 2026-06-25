<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Добавляем поле current_rock_id для хранения текущей породы в забое.
     * 
     * miner_rock — список пород, которые добывались в забое (история, заполняется автоматически)
     * current_rock_id — текущая добываемая порода (выбирает экскаваторщик)
     */
    public function up(): void
    {
        Schema::table('miners', function (Blueprint $table) {
            $table->unsignedBigInteger('current_rock_id')->nullable()->after('active');
            $table->foreign('current_rock_id')
                ->references('id')
                ->on('rocks')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('miners', function (Blueprint $table) {
            $table->dropForeign(['current_rock_id']);
            $table->dropColumn('current_rock_id');
        });
    }
};