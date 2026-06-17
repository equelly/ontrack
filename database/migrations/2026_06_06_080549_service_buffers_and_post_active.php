<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;


return new class extends Migration
{
    public function up(): void
    {
        // Добавляем is_active в таблицу service_posts
        Schema::table('service_posts', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('name');
        });

        // Добавляем настройки буферов
        DB::table('system_settings')->insertOrIgnore([
            [
                'key' => 'service_to_buffer_hours',
                'value' => '20',
                'description' => 'Буфер ТО (моточасы до наступления срока)',
            ],
            [
                'key' => 'service_fueling_buffer_percent',
                'value' => '15',
                'description' => 'Буфер заправки (% остатка топлива для отправки)',
            ],
        ]);
    }

    public function down(): void
    {
        Schema::table('service_posts', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });

        DB::table('system_settings')
            ->whereIn('key', ['service_to_buffer_hours', 'service_fueling_buffer_percent'])
            ->delete();
    }
};
