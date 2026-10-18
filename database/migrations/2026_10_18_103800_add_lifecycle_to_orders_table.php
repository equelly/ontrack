<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Миграция: добавление полей для жизненного цикла заявок на обслуживание.
 *
 * Добавляем:
 *   - status:            new | in_progress | paused | completed | cancelled
 *   - work_type:         mechanic | electric | hydraulic | to1 | to2 |
 *                        tire_service | wheel_tightening | berm | other
 *   - priority:          low | medium | high | critical (по умолчанию medium)
 *   - assigned_to:       id исполнителя (User) — кто взял заявку в работу
 *   - started_at:        когда взяли в работу
 *   - completed_at:      когда завершили
 *   - cancelled_at:      когда отменили
 *   - cancelled_reason:  причина отмены (текст)
 *
 * Миграция существующих заявок:
 *   - Если у заявки уже есть user_exec (исполнитель) и updated_at > created_at
 *     → считаем её выполненной: status='completed', completed_at=updated_at
 *   - Иначе → status='new'
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'status')) {
                $table->string('status', 32)->default('new')->after('category_id');
            }
            if (!Schema::hasColumn('orders', 'work_type')) {
                $table->string('work_type', 32)->nullable()->after('status');
            }
            if (!Schema::hasColumn('orders', 'priority')) {
                $table->string('priority', 16)->default('medium')->after('work_type');
            }
            if (!Schema::hasColumn('orders', 'assigned_to')) {
                $table->unsignedBigInteger('assigned_to')->nullable()->after('priority');
                $table->foreign('assigned_to')->references('id')->on('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('orders', 'started_at')) {
                $table->timestamp('started_at')->nullable()->after('user_exec');
            }
            if (!Schema::hasColumn('orders', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('started_at');
            }
            if (!Schema::hasColumn('orders', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('completed_at');
            }
            if (!Schema::hasColumn('orders', 'cancelled_reason')) {
                $table->text('cancelled_reason')->nullable()->after('cancelled_at');
            }
        });

        // Индексы для быстрых выборок по статусу/исполнителю
        Schema::table('orders', function (Blueprint $table) {
            $table->index('status');
            $table->index('work_type');
            $table->index('assigned_to');
        });

        // === КОНВЕРТАЦИЯ СУЩЕСТВУЮЩИХ ЗАЯВОК ===
        // Если есть user_exec и updated_at > created_at — заявка выполнена
        $convertedCompleted = DB::table('orders')
            ->whereNotNull('user_exec')
            ->whereColumn('updated_at', '>', 'created_at')
            ->update([
                'status'       => 'completed',
                'completed_at' => DB::raw('updated_at'),
            ]);

        // Если есть user_exec, но updated_at == created_at — заявка в работе
        $convertedInProgress = DB::table('orders')
            ->whereNotNull('user_exec')
            ->whereNull('completed_at')
            ->update([
                'status'     => 'in_progress',
                'assigned_to'=> DB::raw('user_exec'),
                'started_at' => DB::raw('created_at'),
            ]);

        // Остальные остаются status='new' (default)

        Log::info("Migrate orders lifecycle: completed={$convertedCompleted}, in_progress={$convertedInProgress}");
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['work_type']);
            $table->dropIndex(['assigned_to']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'status',
                'work_type',
                'priority',
                'assigned_to',
                'started_at',
                'completed_at',
                'cancelled_at',
                'cancelled_reason',
            ]);
        });
    }
};