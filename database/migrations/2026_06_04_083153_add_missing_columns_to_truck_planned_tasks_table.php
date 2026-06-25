<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('truck_planned_tasks', function (Blueprint $table) {
            // Добавляем все недостающие колонки без after()
            if (!Schema::hasColumn('truck_planned_tasks', 'queue_position')) {
                $table->integer('queue_position')->nullable();
            }
            if (!Schema::hasColumn('truck_planned_tasks', 'duration_minutes')) {
                $table->integer('duration_minutes')->nullable();
            }
            if (!Schema::hasColumn('truck_planned_tasks', 'started_at')) {
                $table->timestamp('started_at')->nullable();
            }
            if (!Schema::hasColumn('truck_planned_tasks', 'service_post_id')) {
                $table->unsignedBigInteger('service_post_id')->nullable();
            }
            if (!Schema::hasColumn('truck_planned_tasks', 'to_type')) {
                $table->string('to_type', 10)->nullable();
            }
            if (!Schema::hasColumn('truck_planned_tasks', 'notes')) {
                $table->text('notes')->nullable();
            }
            if (!Schema::hasColumn('truck_planned_tasks', 'completed_at')) {
                $table->timestamp('completed_at')->nullable();
            }
        });

        // Добавляем внешний ключ для service_post_id
        try {
            Schema::table('truck_planned_tasks', function (Blueprint $table) {
                $table->foreign('service_post_id')
                    ->references('id')
                    ->on('service_posts')
                    ->onDelete('set null');
            });
        } catch (\Exception $e) {
            // Внешний ключ уже существует или таблица service_posts не найдена
        }
    }

    public function down(): void
    {
        Schema::table('truck_planned_tasks', function (Blueprint $table) {
            $columns = [];
            foreach (['queue_position', 'duration_minutes', 'started_at', 'service_post_id', 'to_type', 'notes', 'completed_at'] as $col) {
                if (Schema::hasColumn('truck_planned_tasks', $col)) {
                    $columns[] = $col;
                }
            }
            if (in_array('service_post_id', $columns)) {
                try {
                    $table->dropForeign(['service_post_id']);
                } catch (\Exception $e) {}
            }
            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
