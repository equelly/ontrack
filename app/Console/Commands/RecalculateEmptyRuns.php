<?php

namespace App\Console\Commands;

use App\Models\TruckTrip;
use App\Models\MinerDumpDistance;
use App\Models\Dump;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Пересчитать empty_run_km для всех завершённых trip'ов, где значение null.
 *
 * Запускать после:
 *   - Миграции, сделавшей empty_run_km nullable
 *   - Назначения сервисной точки в Панели Мастера
 *   - Добавления недостающих записей в miner_dump_distances
 *
 * Команда не трогает trip'ы с уже заполненным empty_run_km (даже если значение 0
 * — оставляем как есть, это исторические данные).
 *
 * Пример запуска:
 *   php artisan trips:recalculate-empty-runs
 *   php artisan trips:recalculate-empty-runs --days=7  # только за последние 7 дней
 */
class RecalculateEmptyRuns extends Command
{
    protected $signature = 'trips:recalculate-empty-runs
                            {--days= : Только за последние N дней (по умолчанию — все)}';

    protected $description = 'Пересчитать empty_run_km для завершённых trip\'ов где значение null (используя dump_id предыдущего trip или сервисную точку)';

    public function handle(): int
    {
        $this->info('=== Пересчёт empty_run_km для trip\'ов с null ===');

        $servicePointId = Dump::getServicePointId();
        if ($servicePointId) {
            $servicePoint = Dump::find($servicePointId);
            $this->info("Сервисная точка: «{$servicePoint->name_dump}» (id={$servicePointId})");
        } else {
            $this->warn('Сервисная точка НЕ назначена! Trip\'ы без предыдущего рейса останутся с null.');
        }

        // Запрос trip'ов с empty_run_km = null, завершённые
        $query = TruckTrip::whereNull('empty_run_km')
            ->whereNotNull('completed_at')
            ->orderBy('completed_at', 'asc');

        if ($this->option('days')) {
            $days = (int) $this->option('days');
            $query->where('completed_at', '>=', now()->subDays($days));
            $this->info("Фильтр: только за последние {$days} дней");
        }

        $trips = $query->get();
        $this->info("Найдено trip'ов для пересчёта: {$trips->count()}");

        if ($trips->isEmpty()) {
            $this->info('Нет trip\'ов для пересчёта — все уже имеют empty_run_km.');
            return self::SUCCESS;
        }

        $updated = 0;
        $skipped = 0;
        $noStart = 0;
        $noDistance = 0;

        $bar = $this->output->createProgressBar($trips->count());
        $bar->start();

        foreach ($trips as $trip) {
            // Ищем предыдущий завершённый trip
            $previousTrip = TruckTrip::where('truck_id', $trip->truck_id)
                ->where('id', '!=', $trip->id)
                ->whereNotNull('completed_at')
                ->where('completed_at', '<', $trip->completed_at)
                ->orderBy('completed_at', 'desc')
                ->first();

            $startDumpId = $previousTrip?->dump_id ?? $servicePointId;

            if (!$startDumpId) {
                $noStart++;
                $bar->advance();
                continue;
            }

            // Ищем расстояние от startDumpId до miner_id текущего trip
            $distance = MinerDumpDistance::where('miner_id', $trip->miner_id)
                ->where('dump_id', $startDumpId)
                ->value('distance_km');

            if ($distance === null) {
                $noDistance++;
                $bar->advance();
                continue;
            }

            // Сохраняем
            $trip->update(['empty_run_km' => (float) $distance]);
            $updated++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("=== Итог ===");
        $this->info("Обновлено:           {$updated}");
        $this->warn("Нет стартовой точки: {$noStart}");
        $this->warn("Нет записи в distances: {$noDistance}");

        if ($noDistance > 0) {
            $this->newLine();
            $this->error("⚠️  {$noDistance} trip'ов остались с null — для пары (miner_id, dump_id) нет записи в miner_dump_distances.");
            $this->line('   Нужно добавить записи в Таблицу расстояний (Панель Мастера → Забои → edit).');
        }

        if ($noStart > 0) {
            $this->newLine();
            $this->warn("⚠️  {$noStart} trip'ов без предыдущего рейса и без сервисной точки.");
            $this->line('   Назначьте сервисную точку в Панели Мастера → Перегрузки → «Сделать сервисной».');
        }

        return self::SUCCESS;
    }
}