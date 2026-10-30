#!/bin/bash
# ====================================================================
# Скрипт: убирает поле `active` (наследие) из Miner + добавляет fallback для «руда»
#
# Запуск (из корня проекта, где composer.json):
#   bash scripts/remove-miner-active-field.sh
#
# Что делает:
#   1. Заменяет ВСЕ Miner::where('active', true) → Miner::where('status', Miner::STATUS_ACTIVE)
#      (и вариант с active', 1 для RoutePlanningService)
#   2. Удаляет проверку !$miner->active в RouteAssignmentService (избыточна с isWorking)
#   3. Меняет !$miner->active на $miner->status !== Miner::STATUS_ACTIVE в ExcavatorController
#   4. Удаляет toggleMinerStatus() из MainDispatcherPanel
#   5. Меняет $m->active на $m->status === Miner::STATUS_ACTIVE в MainDispatcherPanel
#   6. Убираает 'active' из MinersController (create/update/validate)
#   7. Убираает 'active' из Miner модели ($fillable, $casts, $attributes)
#   8. Добавляет 'руда' => ['руда', 'руда_S', 'руда_Sera'] в ROCK_FALLBACK_CHAIN
#   9. Обновляет вывод в DebugRoutes команде
#
# После запуска:
#   php artisan migrate    (применить миграцию drop_active_column)
#   php artisan optimize:clear
# ====================================================================

set -e  # стоп на первой ошибке

echo "=== 1. Замена Miner::where('active', true) → Miner::where('status', Miner::STATUS_ACTIVE) ==="
# Все PHP файлы, где встречается этот паттерн
FILES=$(grep -rl "Miner::where('active'" app/ --include="*.php" || true)
if [ -n "$FILES" ]; then
    echo "$FILES" | while read -r f; do
        sed -i "s/Miner::where('active', true)/Miner::where('status', Miner::STATUS_ACTIVE)/g" "$f"
        # Вариант с active', 1 (в RoutePlanningService)
        sed -i "s/Miner::where('active', 1)/Miner::where('status', Miner::STATUS_ACTIVE)/g" "$f"
        echo "  ✓ $f"
    done
else
    echo "  — нет совпадений"
fi

echo ""
echo "=== 2. ExcavatorController: !\$miner->active → \$miner->status !== Miner::STATUS_ACTIVE ==="
sed -i 's/!$miner->active/$miner->status !== Miner::STATUS_ACTIVE/g' \
    app/Http/Controllers/ExcavatorController.php
echo "  ✓ app/Http/Controllers/ExcavatorController.php"

echo ""
echo "=== 3. RouteAssignmentService: убрать проверку !\$miner->active (избыточна с isWorking) ==="
# Удаляем строку "} elseif (!$miner->active) {" и следующую за ней "$reason = RouteBlockReason::MINER_INACTIVE;"
# Это безопасно — isWorking() уже проверяет status === STATUS_ACTIVE
sed -i '/} elseif (!\$miner->active) {/,/RouteBlockReason::MINER_INACTIVE;/d' \
    app/Services/RouteAssignmentService.php
echo "  ✓ app/Services/RouteAssignmentService.php"

echo ""
echo "=== 4. MainDispatcherPanel: заменить \$m->active на статус-проверку ==="
sed -i 's/fn(\$m) => \$m->active && (/fn($m) => $m->status === Miner::STATUS_ACTIVE \&\& (/g' \
    app/Livewire/MainDispatcherPanel.php
echo "  ✓ app/Livewire/MainDispatcherPanel.php"

echo ""
echo "=== 5. MainDispatcherPanel: удалить метод toggleMinerStatus() ==="
# Используем awk для удаления блока метода (от "public function toggleMinerStatus" до закрывающей "}")
awk '
/public function toggleMinerStatus/ { skip=1 }
skip && /^    }$/ { skip=0; next }
skip { next }
{ print }
' app/Livewire/MainDispatcherPanel.php > /tmp/main-dispatcher.tmp && mv /tmp/main-dispatcher.tmp app/Livewire/MainDispatcherPanel.php
echo "  ✓ app/Livewire/MainDispatcherPanel.php"

echo ""
echo "=== 6. MinersController: убрать active из create/update/validate ==="
# Удаляем 'active' => true, из create
sed -i "/'active' => true,/d" app/Http/Controllers/User/Miner/MinersController.php
# Удаляем 'active' => 'boolean', из validate
sed -i "/'active' => 'boolean',/d" app/Http/Controllers/User/Miner/MinersController.php
# Удаляем 'active' => $data['active'] ?? $miner->active, из update
sed -i "/'active' => \$data\['active'\] ?? \$miner->active,/d" app/Http/Controllers/User/Miner/MinersController.php
echo "  ✓ app/Http/Controllers/User/Miner/MinersController.php"

echo ""
echo "=== 7. Miner модель: убрать active из \$fillable, \$casts, \$attributes ==="
sed -i "/^        'active',$/d" app/Models/Miner.php
sed -i "/'active' => 'boolean',/d" app/Models/Miner.php
sed -i "/'active' => true,/d" app/Models/Miner.php
echo "  ✓ app/Models/Miner.php"

echo ""
echo "=== 8. DebugRoutes команда: убрать active из вывода ==="
sed -i 's/  active=" . (\$m->active ? '"'"'true'"'"' : '"'"'false'"'"') . "  status={/  status={/g' \
    app/Console/Commands/DebugRoutes.php
sed -i '/$this->line("active: /d' app/Console/Commands/DebugRoutes.php
echo "  ✓ app/Console/Commands/DebugRoutes.php"

echo ""
echo "=== 9. ROCK_FALLBACK_CHAIN: добавить fallback для «руда» ==="
# Добавляем 'руда_S' в существующую цепочку руда_ЦПТ и новую запись для руда
# Используем php для безопасной правки
php -e <<'PHPSCRIPT' 2>/dev/null || \
python3 -c "
import re
path = 'app/Services/RouteAssignmentService.php'
with open(path, encoding='utf-8') as f:
    content = f.read()
# Добавляем руда_S в цепочку руда_ЦПТ (для транзитивности)
content = content.replace(
    \"'руда_ЦПТ' => ['руда_ЦПТ', 'руда', 'руда_Sera'],\",
    \"'руда_ЦПТ' => ['руда_ЦПТ', 'руда', 'руда_S', 'руда_Sera'],\n        'руда'      => ['руда', 'руда_S', 'руда_Sera'],\"
)
with open(path, 'w', encoding='utf-8') as f:
    f.write(content)
print('  ✓ app/Services/RouteAssignmentService.php (ROCK_FALLBACK_CHAIN обновлён)')
"
PHPSCRIPT

echo ""
echo "=== 10. Проверка: остались ли обращения к miner->active ==="
REMAINING=$(grep -rn '\$miner->active\b\|\$m->active\b\|Miner::where.*active' \
    app/ --include='*.php' 2>/dev/null | \
    grep -v 'active_trucks\|active_miners\|activeOrders\|active_count\|active_rock\|active_alerts' | \
    grep -v '//.*active' || true)
if [ -n "$REMAINING" ]; then
    echo "⚠ Найдены остатки (проверь вручную):"
    echo "$REMAINING"
else
    echo "✓ Чисто — все обращения к miner->active убраны"
fi

echo ""
echo "=== 11. Создать миграцию (drop active column) ==="
MIGRATION_FILE="database/migrations/$(date +%Y_%m_%d_%H%M%S)_drop_active_column_from_miners.php"
cat > "$MIGRATION_FILE" <<'MIGRATION'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('miners', 'active')) {
            Schema::table('miners', function (Blueprint $table) {
                $table->dropColumn('active');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('miners', 'active')) {
            Schema::table('miners', function (Blueprint $table) {
                $table->boolean('active')->default(true)->after('name_miner');
            });
            \Illuminate\Support\Facades\DB::table('miners')
                ->where('status', 'active')->update(['active' => true]);
            \Illuminate\Support\Facades\DB::table('miners')
                ->where('status', '!=', 'active')->update(['active' => false]);
        }
    }
};
MIGRATION
echo "  ✓ $MIGRATION_FILE"

echo ""
echo "=========================================="
echo "ГОТОВО! Теперь:"
echo "  php artisan migrate          # дропнуть колонку active"
echo "  php artisan optimize:clear   # очистить кэш"
echo "  php artisan routes:debug ЭКГ-13   # проверить забой"
echo "  php artisan routes:optimize       # перезапустить оптимизацию"
echo "=========================================="
