<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Поля в trucks для отслеживания состояния поломки
        Schema::table('trucks', function (Blueprint $table) {
           
            $table->timestamp('pause_started_at')->nullable()->after('before_breakdown');
        });

        // Поля в truck_trips для быстрых итогов (кешированные суммы)
        Schema::table('truck_trips', function (Blueprint $table) {
            $table->timestamp('paused_at')->nullable()->after('started_at');
            $table->string('pause_type')->nullable()->after('paused_at');
        });

        // Отдельная таблица для всех пауз (детальная история)
        Schema::create('trip_pauses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('truck_trip_id')->constrained()->onDelete('cascade');
            $table->foreignId('truck_id')->constrained()->onDelete('cascade');
            
            // Тип задержки
            $table->enum('type', [
                'breakdown',           // Поломка
                'road_works',          // Дорожные работы
                'waiting_loading',     // Ожидание погрузки
                'waiting_unloading',   // Ожидание выгрузки
                'weather',             // Погодные условия
                'traffic',             // Пробки
                'other',               // Другое
            ]);
            
            $table->string('reason')->nullable();        // Текстовое описание причины
            $table->text('notes')->nullable();           // Дополнительные заметки
            $table->timestamp('started_at');             // Начало паузы
            $table->timestamp('ended_at')->nullable();   // Конец паузы
            $table->integer('duration_seconds')->default(0); // Длительность в секундах
            
            $table->timestamps();
            
            $table->index(['truck_id', 'type']);
            $table->index('started_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_pauses');
        
        Schema::table('trucks', function (Blueprint $table) {
            $table->dropColumn(['before_breakdown', 'pause_started_at']);
        });

        Schema::table('truck_trips', function (Blueprint $table) {
            $table->dropColumn(['paused_at', 'pause_type']);
        });
    }
};
