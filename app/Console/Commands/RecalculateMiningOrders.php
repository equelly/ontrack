<?php

namespace App\Console\Commands;

use App\Models\Miner;
use App\Models\Dump;
use App\Models\Rock;
use App\Models\Zone;
use App\Models\MiningOrder;
use App\Models\MinerDumpDistance;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RecalculateMiningOrders extends Command
{
    protected $signature = 'mining:recalculate-orders {--mode=balance : Режим расчёта (balance, volume, distance)}';
    protected $description = 'Пересчитать mining_orders на основе расстояний и доступных зон';

    protected array $stats = [
        'created' => 0,
        'updated' => 0,
        'skipped' => 0,
        'deleted' => 0,
    ];

    public function handle(): int
    {
        $mode = $this->option('mode');
        $allowedModes = ['balance', 'volume', 'distance'];
        
        if (!in_array($mode, $allowedModes)) {
            $this->error("Неверный режим: {$mode}. Допустимые: " . implode(', ', $allowedModes));
            return Command::FAILURE;
        }

        $this->info("🔄 Пересчёт mining_orders (режим: {$mode})");
        $this->newLine();

        DB::transaction(function () use ($mode) {
            // 1. Деактивируем существующие маршруты (не удаляем, чтобы сохранить статистику)
            $this->stats['deleted'] = MiningOrder::query()->update(['active' => false]);
            $this->info("📉 Деактивировано маршрутов: {$this->stats['deleted']}");

            // 2. Загружаем данные
            $miners = Miner::where('active', true)->with('rocks')->get();
            $dumps = Dump::with(['zones.rocks'])->get();
            $distances = MinerDumpDistance::all()->keyBy(fn($d) => "{$d->miner_id}_{$d->dump_id}");

            $this->info("📊 Забоев: {$miners->count()}, Перегрузок: {$dumps->count()}");
            $this->newLine();

            // 3. Создаём новые маршруты
            $bar = $this->output->createProgressBar($miners->count());

            foreach ($miners as $miner) {
                foreach ($dumps as $dump) {
                    // Получаем все породы, которые принимает этот dump (через зоны)
                    $dumpRockIds = $dump->zones->flatMap->rocks->pluck('id')->unique();

                    foreach ($dumpRockIds as $rockId) {
                        $this->createOrUpdateOrder($miner, $dump, $rockId, $distances, $mode);
                    }
                }
                $bar->advance();
            }

            $bar->finish();
            $this->newLine(2);
        });

        // 4. Выводим статистику
        $this->info("✅ Готово!");
        $this->table(
            ['Метрика', 'Значение'],
            [
                ['Создано новых', $this->stats['created']],
                ['Обновлено', $this->stats['updated']],
                ['Пропущено (нет расстояния)', $this->stats['skipped']],
                ['Всего активных', MiningOrder::where('active', true)->count()],
            ]
        );

        return Command::SUCCESS;
    }

    protected function createOrUpdateOrder(
        Miner $miner,
        Dump $dump,
        int $rockId,
        $distances,
        string $mode
    ): void {
        // Ищем расстояние
        $distanceKey = "{$miner->id}_{$dump->id}";
        $distanceRecord = $distances->get($distanceKey);

        if (!$distanceRecord) {
            $this->stats['skipped']++;
            return;
        }

        // Проверяем есть ли доступная зона с этой породой
        $hasAvailableZone = $dump->zones()
            ->whereHas('rocks', fn($q) => $q->where('rocks.id', $rockId))
            ->where(function ($q) {
                $q->where('delivery', true)
                  ->orWhereRaw('volume < capacity');
            })
            ->exists();

        if (!$hasAvailableZone) {
            return; // Нет доступных зон — не создаём
        }

        // Рассчитываем score
        $score = $this->calculateScore($distanceRecord, $dump, $mode);

        // Ищем существующий маршрут или создаём новый
         $order = MiningOrder::query()
            ->where('miner_id', $miner->id)
            ->where('dump_id', $dump->id)
            ->where('rock_id', $rockId)
            ->first();

        if ($order) {
            // Обновляем существующий
            $order->update([
                'active' => true,
                'distance_km' => $distanceRecord->distance_km,
                'score' => $score,
            ]);
            $this->stats['updated']++;
        } else {
            // Создаём новый
            MiningOrder::create([
                'miner_id' => $miner->id,
                'dump_id' => $dump->id,
                'rock_id' => $rockId,
                'distance_km' => $distanceRecord->distance_km,
                'score' => $score,
                'active' => true,
                'wrr_cursor' => 0,
            ]);
            $this->stats['created']++;
        }
    }

    protected function calculateScore($distanceRecord, Dump $dump, string $mode): float
    {
        $distance = $distanceRecord->distance_km;
        $totalZoneVolume = $dump->zones->sum('volume');
        $dumpCapacity = $dump->capacity ?? $totalZoneVolume * 1.5;

        return match ($mode) {
            'balance' => $this->calculateBalanceScore($distance, $totalZoneVolume, $dumpCapacity),
            'volume' => $this->calculateVolumeScore($distance, $totalZoneVolume),
            'distance' => $this->calculateDistanceScore($distance),
            default => $this->calculateBalanceScore($distance, $totalZoneVolume, $dumpCapacity),
        };
    }

    protected function calculateBalanceScore(float $distance, float $volume, float $capacity): float
    {
        $volumePercent = $capacity > 0 ? ($volume / $capacity) * 100 : 0;
        $volumeScore = max(0, 100 - $volumePercent);
        $distancePenalty = $distance * 10;
        $distanceScore = max(0, 100 - $distancePenalty);
        
        return round(($volumeScore * 0.3) + ($distanceScore * 0.7), 2);
    }

    protected function calculateVolumeScore(float $distance, float $volume): float
    {
        $inverseVolume = (1 / ($volume + 1)) * 1000;
        $distancePenalty = $distance * 3;
        
        return round($inverseVolume - $distancePenalty, 2);
    }

    protected function calculateDistanceScore(float $distance): float
    {
        return round((1 / ($distance + 0.1)) * 100, 2);
    }
}