<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('truck_trips', function (Blueprint $table) {
            // Добавляем loaded_at если не существует
            if (!Schema::hasColumn('truck_trips', 'loaded_at')) {
                $table->datetime('loaded_at')->nullable()->after('started_at');
            }
            
            // Добавляем wait_start если не существует (время начала ожидания)
            if (!Schema::hasColumn('truck_trips', 'wait_start')) {
                $table->datetime('wait_start')->nullable()->after('started_at');
            }
            
            // Добавляем load_start если не существует (время начала погрузки)
            if (!Schema::hasColumn('truck_trips', 'load_start')) {
                $table->datetime('load_start')->nullable()->after('wait_start');
            }
        });
    }

    public function down(): void
    {
        Schema::table('truck_trips', function (Blueprint $table) {
            $columns = [];
            if (Schema::hasColumn('truck_trips', 'loaded_at')) {
                $columns[] = 'loaded_at';
            }
            if (Schema::hasColumn('truck_trips', 'wait_start')) {
                $columns[] = 'wait_start';
            }
            if (Schema::hasColumn('truck_trips', 'load_start')) {
                $columns[] = 'load_start';
            }
            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
