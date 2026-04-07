<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('miners', function (Blueprint $table) {
            // Статус: active, breakdown, maintenance, dismantling, access_setup
            $table->string('status')->default('active')->after('active');
            
            // Время начала задержки
            $table->timestamp('status_changed_at')->nullable()->after('status');
            
            // Кто изменил статус
            $table->unsignedBigInteger('status_changed_by')->nullable()->after('status_changed_at');
            
            $table->foreign('status_changed_by')->references('id')->on('users')->onDelete('set null');
            $table->index('status');
            $table->index('status_changed_at');
        });
    }

    public function down(): void
    {
        Schema::table('miners', function (Blueprint $table) {
            $table->dropForeign(['status_changed_by']);
            $table->dropColumn(['status', 'status_changed_at', 'status_changed_by']);
        });
    }
};
