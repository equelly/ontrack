<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Таблица запросов на обваловку зоны.
 *
 * Обваловка — это отсыпка предохранительного вала для безопасности
 * ведения горных работ. Может потребоваться как при переполнении зоны,
 * так и заранее (например, если из зоны отгружают горную массу и нужно
 * обновить защитный вал).
 *
 * Запрос обваловки имеет приоритет над обычными маршрутами:
 * пока зона под обваловкой, обычные маршруты на неё не назначаются,
 * а свободные самосвалы направляются на обваловку в первую очередь.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('berm_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zone_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dump_id')->constrained()->cascadeOnDelete();

            // Сколько самосвалов нужно для обваловки
            $table->unsignedSmallInteger('trucks_needed')->default(1);

            // Сколько уже назначено / завершили разгрузку
            $table->unsignedSmallInteger('trucks_assigned')->default(0);
            $table->unsignedSmallInteger('trucks_completed')->default(0);

            // Порода, которой отсыпать вал (если null — любая подходящая)
            $table->foreignId('rock_id')->nullable()->constrained()->nullOnDelete();

            // Статус запроса
            // pending    — только создан, ждёт назначения самосвалов
            // in_progress — самосвалы назначены, едут/разгружаются
            // completed  — N самосвалов завершили разгрузку
            // cancelled   — мастер отменил запрос вручную
            $table->enum('status', ['pending', 'in_progress', 'completed', 'cancelled'])
                  ->default('pending');

            // Кто создал и когда завершила обваловка
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // Индексы
            $table->index(['zone_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('berm_requests');
    }
};
