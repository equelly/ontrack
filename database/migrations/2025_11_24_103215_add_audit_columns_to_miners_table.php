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
        
        Schema::table('miners', function (Blueprint $table) {
            // 🆕 Аудит-колонки (как было)
            $table->timestamp('last_updated_at')->nullable()->after('updated_at');
            $table->unsignedBigInteger('last_updated_by')->nullable()->after('last_updated_at');
            $table->index(['last_updated_at', 'last_updated_by']);
        });

        // 🆕 Внешний ключ на таблицу users
        Schema::table('miners', function (Blueprint $table) {
            $table->foreign('last_updated_by')
                ->references('id') 
                ->on('users')
                ->onDelete('set null');  // Если пользователь удалён — ставим null
        });
        }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('miners', function (Blueprint $table) {
            // Сначала удаляем внешний ключ
        $table->dropForeign(['last_updated_by']);

        // Потом индекс и колонки
        $table->dropIndex(['last_updated_at', 'last_updated_by']);
        $table->dropColumn(['last_updated_at', 'last_updated_by']);
        });
    }
};
