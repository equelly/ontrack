<?php

namespace App\Console\Commands;

use App\Models\Miner;
use App\Models\MiningOrder;
use App\Models\Zone;
use App\Services\RouteOptimizerService;
use App\Services\RouteAssignmentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Диагностика маршрутов конкретного забоя.
 *
 * Запуск:
 *   php artisan routes:debug ЭКГ-13
 *   php artisan routes:debug 13        (по ID)
 *
 * Показывает:
 *   - current_rock_id и имя породы забоя
 *   - Все MiningOrder забоя (с dump, distance, active, zone_id)
 *   - Для каждого маршрута — доступные зоны через getAvailableZonesForRock
 *     (с delivery, volume/capacity, fill %, принимаемые породы)
 *   - Причину: почему маршрут не активирован (нет зон / зоны заняты / порода не подходит)
 */
class DebugRoutes extends Command
{
    protected $signature = 'routes:debug
                            {miner : Имя или ID забоя (например ЭКГ-13 или 13)}';

    protected $description = 'Диагностика маршрутов конкретного забоя: почему нет активного маршрута';

    public function handle(RouteOptimizerService $optimizer): int
    {
        $arg = $this->argument('miner');

        // Поиск забоя по ID или по имени
        $miner = is_numeric($arg)
            ? Miner::find((int) $arg)
            : Miner::where('name_miner', $arg)->first();

        if (!$miner) {
            // Попробуем частичное совпадение по имени
            $miner = Miner::where('name_miner', 'like', "%{$arg}%")->first();
        }

        if (!$miner) {
            $this->error("Забой «{$arg}» не найден.");
            $this->line("Доступные забои:");
            foreach (Miner::orderBy('name_miner')->get() as $m) {
                $this->line("  ID={$m->id}  {$m->name_miner}  status={$m->status}");
            }
            return Command::FAILURE;
        }

        $this->info("=== Забой: {$miner->name_miner} (ID={$miner->id}) ===");
        $this->line("status: {$miner->status}");
        $this->line("target_load_time: " . ($miner->target_load_time ?? '—') . " сек");

        $rock = $miner->currentRock;
        $this->line("current_rock_id: " . ($miner->current_rock_id ?? 'NULL') . ($rock ? " ({$rock->name_rock})" : ''));
        $this->line("");

        // Проверка: активный ли забой (неактивные не участвуют в оптимизации)
        if ($miner->status !== Miner::STATUS_ACTIVE) {
            $this->warn("⚠ Забой не в статусе active — не участвует в оптимизации!");
            $this->line("   Статус: {$miner->status} — нужно перевести в active.");
            $this->line("");
        }

        if (!$rock) {
            $this->error("⚠ У забоя не задана current_rock (порода)! Без породы маршруты не назначаются.");
            $this->line("   Установи породу через панель экскаватора или через БД:");
            $this->line("   UPDATE miners SET current_rock_id = X WHERE id = {$miner->id};");
            return Command::FAILURE;
        }

        // Все MiningOrder этого забоя
        $orders = MiningOrder::where('miner_id', $miner->id)
            ->with(['dump.zones.rocks', 'zone'])
            ->get();

        if ($orders->isEmpty()) {
            $this->warn("У забоя нет ни одного MiningOrder (маршрута забой→отвал).");
            $this->line("Создай пары через: php artisan routes:init");
            $this->line("Или через UI диспетчера: Настройки → Аварийные действия → Сгенерировать все пары.");
            return Command::FAILURE;
        }

        $this->info("=== Маршруты (MiningOrder) забоя: {$orders->count()} шт. ===");

        $routeService = app(RouteAssignmentService::class);
        $acceptableRockNames = $routeService::ROCK_FALLBACK_CHAIN[$rock->name_rock] ?? [$rock->name_rock];

        $this->line("Порода забоя: {$rock->name_rock}");
        $this->line("Fallback-цепочка пород (зоны принимают любую из): " . implode(', ', $acceptableRockNames));
        $this->line("");

        $activeCount = 0;
        $availableZonesTotal = 0;

        foreach ($orders as $order) {
            $distance = $order->distance_km;
            if (!$distance) {
                $distance = \App\Models\MinerDumpDistance::where('miner_id', $order->miner_id)
                    ->where('dump_id', $order->dump_id)
                    ->value('distance_km');
            }

            $this->line(str_repeat('─', 70));
            $this->line("MiningOrder #{$order->id}");
            $this->line("  Отвал: {$order->dump?->name_dump} (ID={$order->dump_id})");
            $this->line("  Расстояние: " . ($distance ?? 'НЕТ') . " км");
            $this->line("  Вес: {$order->weight}, active: " . ($order->active ? 'true' : 'false'));
            $this->line("  zone_id в БД: " . ($order->zone_id ?? 'NULL') . ($order->zone ? " ({$order->zone->name_zone})" : ''));
            $this->line("  wrr_cursor: {$order->wrr_cursor}");

            if (!$distance) {
                $this->warn("  ⚠ НЕТ расстояния — маршрут будет отфильтрован в getAllRoutesWithScore.");
                $this->line("    Добавь запись в miner_dump_distances: (miner_id={$miner->id}, dump_id={$order->dump_id})");
                continue;
            }

            // Получаем доступные зоны через тот же метод, что использует оптимизатор
            $zones = $this->getAvailableZonesForRock($order->dump_id, $rock->id);

            if ($zones->isEmpty()) {
                $this->warn("  ⚠ НЕТ доступных зон для породы «{$rock->name_rock}» — маршрут будет отфильтрован.");

                // Показываем ВСЕ зоны отвала (даже недоступные) для диагностики
                $allZones = Zone::where('dump_id', $order->dump_id)->with('rocks')->get();
                $this->line("    Все зоны отвала {$order->dump_id} (включая закрытые/полные):");
                foreach ($allZones as $z) {
                    $rocksOnZone = $z->rocks->pluck('name_rock')->implode(', ');
                    $fillPct = $z->capacity > 0 ? round(($z->volume / $z->capacity) * 100, 1) : 0;
                    $this->line("      Zone #{$z->id} «{$z->name_zone}»");
                    $this->line("        delivery=" . ($z->delivery ? 'true' : 'false') . ", volume={$z->volume}/{$z->capacity} (fill {$fillPct}%)");
                    $this->line("        принимает породы: {$rocksOnZone}");
                    $accepts = $z->rocks->whereIn('name_rock', $acceptableRockNames)->isNotEmpty();
                    $this->line("        принимает породу забоя «{$rock->name_rock}»: " . ($accepts ? '✅ ДА' : '❌ НЕТ'));
                }
                continue;
            }

            $this->info("  ✅ Есть доступные зоны: {$zones->count()} шт.");
            $availableZonesTotal += $zones->count();

            foreach ($zones as $z) {
                $fillPct = $z->capacity > 0 ? round(($z->volume / $z->capacity) * 100, 1) : 0;
                $this->line("    Zone #{$z->id} «{$z->name_zone}» — fill {$fillPct}% (volume={$z->volume}/{$z->capacity})");
            }

            if ($order->active) {
                $activeCount++;
            }
        }

        $this->line("");
        $this->info("=== ИТОГ ===");
        $this->line("Активных маршрутов: {$activeCount} из {$orders->count()}");
        $this->line("Всего доступных зон на всех отвалах: {$availableZonesTotal}");

        if ($availableZonesTotal === 0) {
            $this->error("❌ Нет ни одной доступной зоны ни на одном отвалe.");
            $this->line("Возможные причины:");
            $this->line("  1. Зоны не принимают породу забоя (rock_zone связь)");
            $this->line("  2. Все зоны закрыты (delivery=false)");
            $this->line("  3. Все зоны заполнены (volume >= capacity)");
        } elseif ($activeCount === 0) {
            $this->warn("⚠ Маршруты не активированы. Запусти оптимизацию:");
            $this->line("  php artisan routes:optimize");
            $this->line("");
            $this->line("После оптимизации проверь лог:");
            $this->line("  tail -f storage/logs/laravel.log | grep assignByRounds");
        }

        return Command::SUCCESS;
    }

    /**
     * Доступные зоны для породы на отвалe (копия метода из RouteOptimizerService).
     */
    protected function getAvailableZonesForRock(int $dumpId, int $rockId)
    {
        $rock = \App\Models\Rock::find($rockId);
        if (!$rock) {
            return collect();
        }

        $routeService = app(RouteAssignmentService::class);
        $acceptableRockNames = $routeService::ROCK_FALLBACK_CHAIN[$rock->name_rock] ?? [$rock->name_rock];

        return Zone::where('dump_id', $dumpId)
            ->where('delivery', true)
            ->whereRaw('volume < capacity')
            ->whereHas('rocks', function ($q) use ($acceptableRockNames) {
                $q->whereIn('rocks.name_rock', $acceptableRockNames);
            })
            ->orderBy('volume', 'asc')
            ->get();
    }
}
