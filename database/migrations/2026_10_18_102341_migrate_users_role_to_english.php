<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Миграция: role → английский (operational/maintenance/admin).
 *
 * Текущее состояние:
 *   role = 'эксплуатационный' (string, на русском)
 *   role = 'обслуживающий'    (string, на русском)
 *   role = 'admin'            (string)
 *   role = 'driver' / 'dispatcher' / 'master' / 'excavator_operator'
 *     ← старые записи, где role хранил position (до того как добавили position)
 *
 * После миграции:
 *   role      = operational | maintenance | admin   (категория)
 *   position  = driver | master | dispatcher | excavator_operator
 *             | mechanic | electric | maintenance_dispatcher | admin  (должность)
 *
 * Конвертация:
 *   'эксплуатационный' → role='operational'
 *   'обслуживающий'    → role='maintenance'
 *   'admin'            → role='admin', position='admin'
 *   Если role хранил position (driver/master/dispatcher/excavator_operator):
 *     → role вычисляется по position, position переносится без изменений
 */
return new class extends Migration
{
    private const POSITION_TO_ROLE = [
        'driver'              => 'operational',
        'master'              => 'operational',
        'dispatcher'          => 'operational',
        'excavator_operator'  => 'operational',
        'mechanic'            => 'maintenance',
        'electric'            => 'maintenance',
        'maintenance_dispatcher' => 'maintenance',
        'dispather_top'       => 'maintenance',  // старое название (с опечаткой) → maintenance
        'admin'               => 'admin',
    ];

    public function up(): void
    {
        // Убедимся что колонка position существует (если миграция add_position не запущена)
        if (!Schema::hasColumn('users', 'position')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('position')->nullable()->after('role');
            });
        }

        // === КОНВЕРТАЦИЯ СУЩЕСТВУЮЩИХ ЗАПИСЕЙ ===
        // Перебираем всех пользователей и приводим role/position к стандарту
        $users = DB::table('users')->get(['id', 'name', 'role', 'position']);
        $converted = 0;

        foreach ($users as $u) {
            $role = trim((string) ($u->role ?? ''));
            $position = trim((string) ($u->position ?? ''));

            $newRole = null;
            $newPosition = $position ?: null;

            // 1. role уже на английском (operational/maintenance/admin) — оставляем
            if (in_array($role, ['operational', 'maintenance', 'admin'], true)) {
                $newRole = $role;
                // Если role=admin, но position пустой — ставим position=admin
                if ($role === 'admin' && !$newPosition) {
                    $newPosition = 'admin';
                }
            }
            // 2. role на русском (эксплуатационный / обслуживающий)
            elseif ($role === 'эксплуатационный') {
                $newRole = 'operational';
            }
            elseif ($role === 'обслуживающий') {
                $newRole = 'maintenance';
            }
            // 3. role хранил position (старые записи, до миграции add_position)
            elseif (array_key_exists($role, self::POSITION_TO_ROLE)) {
                $newRole = self::POSITION_TO_ROLE[$role];
                // position переносим из role (если position пустой)
                if (!$newPosition) {
                    $newPosition = $role === 'dispather_top' ? 'maintenance_dispatcher' : $role;
                }
            }
            // 4. Неизвестное значение role — логируем как предупреждение,
            //    ставим 'operational' по умолчанию (минимальный доступ)
            else {
                Log::warning("MigrateRoleToEnglish: неизвестный role='{$role}' для user #{$u->id} ({$u->name})");
                $newRole = 'operational';
            }

            // Если position есть в POSITION_TO_ROLE — сверим role:
            // если role не соответствует position, обновим role по position
            // (например: position=mechanic, role=operational — баг, нужно maintenance)
            if ($newPosition && array_key_exists($newPosition, self::POSITION_TO_ROLE)) {
                $expectedRole = self::POSITION_TO_ROLE[$newPosition];
                if ($expectedRole !== 'admin') {
                    $newRole = $expectedRole;
                }
            }

            DB::table('users')
                ->where('id', $u->id)
                ->update([
                    'role'     => $newRole,
                    'position' => $newPosition,
                ]);
            $converted++;
        }

        Log::info("MigrateRoleToEnglish: конвертировано {$converted} пользователей");
    }

    public function down(): void
    {
        // Обратная конвертация: operational → 'эксплуатационный', maintenance → 'обслуживающий'
        $map = [
            'operational' => 'эксплуатационный',
            'maintenance' => 'обслуживающий',
            'admin'       => 'admin',
        ];

        foreach ($map as $eng => $rus) {
            DB::table('users')->where('role', $eng)->update(['role' => $rus]);
        }
    }
};