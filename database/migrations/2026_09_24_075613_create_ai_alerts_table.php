<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Таблица AI-алертов — аномалии и отклонения, обнаруженные системой.
 *
 * Алерты персистентны: хранятся в БД, пока диспетчер не подтвердит.
 * Не исчезают автоматически — чтобы не пропустить важное решение.
 *
 * Типы алертов:
 * - truck_anomaly: аномалия самосвала (время рейса, погрузки отклонилось от нормы)
 * - zone_overflow: зона скоро переполнится (прогноз по тренду)
 * - productivity_drop: падение производительности забоя
 * - empty_run_high: высокий холостой пробег парка
 * - maintenance_predict: предсказание необходимости ТО
 *
 * Уровни важности:
 * - info: информационно
 * - warning: требует внимания
 * - critical: критично, требует немедленного решения
 *
 * Статусы:
 * - new: новый, не просмотрен
 * - acknowledged: диспетчер увидел и принял к сведению
 * - resolved: проблема устранена
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // truck_anomaly, zone_overflow, productivity_drop, empty_run_high, maintenance_predict
            $table->string('severity')->default('warning'); // info, warning, critical
            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable(); // дополнительные данные (truck_id, miner_id, zone_id, metrics)
            $table->string('entity_type')->nullable(); // truck, miner, zone, fleet
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('status')->default('new'); // new, acknowledged, resolved
            $table->timestamp('acknowledged_at')->nullable();
            $table->unsignedBigInteger('acknowledged_by')->nullable();
            $table->foreign('acknowledged_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['entity_type', 'entity_id']);
            $table->index(['type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_alerts');
    }
};