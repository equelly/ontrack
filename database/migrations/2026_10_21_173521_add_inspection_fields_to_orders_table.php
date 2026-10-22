<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Миграция: добавление полей для оценки ремонта механиком/электриком.
 *
 * После осмотра оборудования механик/электрик устанавливает:
 *   - estimated_minutes: примерное время на ремонт (в минутах)
 *   - inspector_id: кто осмотрел (FK → users.id)
 *   - inspected_at: когда осмотрел
 *
 * Эти данные используются MaintenancePlannerService для балансировки парка:
 *   - Зная оценочное время каждой заявки + свободные посты →
 *     можно спланировать какие работы делать в первую очередь
 *   - Сопоставить с требуемым/фактическим количеством самосвалов на забоях →
 *     определить, какие работы выгоднее проводить сейчас
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'estimated_minutes')) {
                $table->integer('estimated_minutes')->nullable()->after('priority');
            }
            if (!Schema::hasColumn('orders', 'inspector_id')) {
                $table->unsignedBigInteger('inspector_id')->nullable()->after('estimated_minutes');
                $table->foreign('inspector_id')->references('id')->on('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('orders', 'inspected_at')) {
                $table->timestamp('inspected_at')->nullable()->after('inspector_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'inspector_id')) {
                $table->dropForeign(['inspector_id']);
            }
            $table->dropColumn(['estimated_minutes', 'inspector_id', 'inspected_at']);
        });
    }
};