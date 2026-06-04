<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Для MySQL - изменяем enum
        // Для SQLite - enum хранится как text, поэтому просто меняем на string

        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            // MySQL: расширяем enum
            DB::statement("ALTER TABLE truck_planned_tasks MODIFY COLUMN task_type ENUM('maintenance', 'fueling', 'inspection', 'tire_inflation', 'wheel_tightening')");
        } else {
            // SQLite: пересоздаём таблицу с string полем
            Schema::create('truck_planned_tasks_new', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('truck_id');
                $table->string('task_type', 50);
                $table->timestamp('scheduled_at')->nullable();
                $table->boolean('completed')->default(false);
                $table->timestamp('completed_at')->nullable();
                $table->integer('queue_position')->nullable();
                $table->integer('duration_minutes')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->unsignedBigInteger('service_post_id')->nullable();
                $table->string('to_type', 10)->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->foreign('truck_id')->references('id')->on('trucks')->onDelete('cascade');
            });

            // Копируем данные
            $columns = 'id, truck_id, task_type, scheduled_at, completed, completed_at, queue_position, duration_minutes, started_at, service_post_id, to_type, notes, created_at, updated_at';
            DB::statement("INSERT INTO truck_planned_tasks_new ($columns) SELECT $columns FROM truck_planned_tasks");

            // Удаляем старую и переименовываем
            Schema::drop('truck_planned_tasks');
            Schema::rename('truck_planned_tasks_new', 'truck_planned_tasks');
        }
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE truck_planned_tasks MODIFY COLUMN task_type ENUM('maintenance', 'fueling', 'inspection')");
        }
        
    }
};
