<?php

namespace App\Console\Commands;

use App\Models\MinerDumpDistance;
use App\Models\MiningOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Синхронизировать distance_km в mining_orders из miner_dump_distances.
 *
 * Используется:
 *   - При первом развёртывании системы на новом карьере
 *   - Для исправления старых данных (если Observer не был зарегистрирован)
 *   - В аварийных случаях (если что-то пошло не так)
 *
 * Запуск:
 *   php artisan routes:sync-distances
 *
 * Логика:
 *   - Если MiningOrder не существует — создаём (active=false)
 *   - Если существует, но distance_km отличается — обновляем
 *   - Если совпадает — пропускаем
 *
 * В обычном режиме работы эта команда не нужна — MinerDumpDistanceObserver
 * автоматически синхронизирует distance_km при добавлении/изменении расстояний.
 */
class SyncRoutesDistances extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'routes:sync-distances
                            {--dry-run : Только показать, что будет изменено, без фактического обновления}';

    /**
     * The console command description.
     */
    protected $description = 'Синхронизировать distance_km в mining_orders из miner_dump_distances (создаёт недостающие маршруты, обновляет существующие)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('режим dry-run: изменения не применяются');
            $this->newLine();
        }

        $distances = MinerDumpDistance::all();

        if ($distances->isEmpty()) {
            $this->warn('В таблице miner_dump_distances нет записей.');
            return self::SUCCESS;
        }

        $this->info("Найдено записей в miner_dump_distances: {$distances->count()}");
        $this->newLine();

        $created = 0;
        $updated = 0;
        $skipped = 0;

        $bar = $this->output->createProgressBar($distances->count());
        $bar->start();

        foreach ($distances as $distance) {
            $order = MiningOrder::where('miner_id', $distance->miner_id)
                ->where('dump_id', $distance->dump_id)
                ->first();

            if (!$order) {
                // Маршрута нет — создаём
                if (!$dryRun) {
                    MiningOrder::create([
                        'miner_id'    => $distance->miner_id,
                        'dump_id'     => $distance->dump_id,
                        'distance_km' => $distance->distance_km,
                        'active'      => false,
                        'weight'      => 100,
                        'wrr_cursor'  => 0,
                    ]);
                }
                $created++;
            } elseif ((float) $order->distance_km !== (float) $distance->distance_km) {
                // Маршрут есть, но distance_km отличается — обновляем
                if (!$dryRun) {
                    $order->update(['distance_km' => $distance->distance_km]);
                }
                $updated++;
            } else {
                $skipped++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // Отчёт
        $this->info('=== Отчёт о синхронизации ===');
        $this->info("Всего записей обработано: {$distances->count()}");
        $this->info("Создано новых маршрутов: {$created}");

        if ($dryRun) {
            $this->warn("(dry-run) Было бы создано: {$created}");
            $this->warn("(dry-run) Было бы обновлено: {$updated}");
        } else {
            $this->info("Обновлено distance_km: {$updated}");
        }

        $this->info("Пропущено (уже актуально): {$skipped}");

        // Логирование
        Log::info('routes:sync-distances выполнена', [
            'total'   => $distances->count(),
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'dry_run' => $dryRun,
        ]);

        return self::SUCCESS;
    }
}
