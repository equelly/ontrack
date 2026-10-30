<?php

namespace App\Console\Commands;

use App\Services\Simulation\SimulationConfig;
use App\Services\Simulation\SimulationEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Сравнение систем распределения: факт (Modular) vs наша система.
 *
 * Запуск: php artisan simulation:compare {json_path}
 *
 * JSON-файл содержит:
 *   - shift: {date, type}
 *   - metrics_from_modular: {avg_speed, avg_load_time, avg_unload_time}
 *   - events: [{time, entity, entity_id, type, duration_min}]
 *   - actual_results: {avg_distance, truck_kio, miner_kio, ...}
 *
 * Инфраструктура (забои, самосвалы, зоны, расстояния, породы, delivery)
 * загружается из БД — настраивается через панель мастера.
 */
class CompareSimulation extends Command
{
    protected $signature = 'simulation:compare {json}';
    protected $description = 'Сравнение систем распределения: факт (Modular) vs симуляция (наша)';

    public function handle(): int
    {
        $jsonPath = $this->argument('json');

        $this->info("=== Сравнение систем распределения ===");
        $this->info("Конфиг: {$jsonPath}");

        // 1. Загрузка конфига (БД + JSON)
        $this->info("\nЗагрузка данных...");
        try {
            $config = new SimulationConfig($jsonPath);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->info("  Смена: " . $config->shiftStart->format('d.m H:i') . " → " . $config->shiftEnd->format('d.m H:i'));
        $this->info("  Забоев: {$config->miners->count()}");
        $this->info("  Самосвалов: {$config->trucks->count()}");
        $this->info("  Зон: {$config->zones->count()}");
        $this->info("  Маршрутов: {$config->orders->count()}");
        $this->info("  Расстояний: {$config->distances->count()}");
        $this->info("  Событий: " . count($config->events));
        $this->info("  Средняя скорость: {$config->avgSpeed} км/ч");
        $this->info("  Время погрузки: {$config->avgLoadTime} мин");
        $this->info("  Время разгрузки: {$config->avgUnloadTime} мин");
        $this->info("  Тиков: " . $config->getTotalTicks() . " (шаг {$config->timeStepMinutes} мин)");

        // 2. Фактические результаты из JSON (Modular)
        $actual = $config->getActualResults();
        if (empty($config->actualResults)) {
            $this->warn("\n⚠ В JSON нет actual_results — сравнение невозможно!");
            $this->info("Запускаем только симуляцию (без сравнения):");
        }

        // 3. Симуляция
        $this->info("\nЗапуск симуляции...");
        $engine = new SimulationEngine($config);
        $simulated = $engine->run();

        // 4. Вывод
        $this->info("\n" . str_repeat('=', 80));
        $this->info("СРАВНИТЕЛЬНАЯ ТАБЛИЦА");
        $this->info(str_repeat('=', 80));

        $headers = ['Метрика', 'Факт (Modular)', 'Симуляция (наша)', 'Разница'];
        $rows = [
            ['Среднее расстояние (км)', $actual['avg_distance'], $simulated['avg_distance'],
                $this->diff($actual['avg_distance'], $simulated['avg_distance'])],
            ['Холостой пробег (км)', $actual['total_empty_km'], $simulated['total_empty_km'],
                $this->diff($actual['total_empty_km'], $simulated['total_empty_km'])],
            ['Холостой (%)', $actual['empty_pct'] . '%', $simulated['empty_pct'] . '%',
                $this->diff($actual['empty_pct'], $simulated['empty_pct'])],
            ['КИО самосвалов (%)', $actual['truck_kio'] . '%', $simulated['truck_kio'] . '%',
                $this->diff($actual['truck_kio'], $simulated['truck_kio'])],
            ['КИО экскаваторов (%)', $actual['miner_kio'] . '%', $simulated['miner_kio'] . '%',
                $this->diff($actual['miner_kio'], $simulated['miner_kio'])],
            ['Простои самосвалов (мин)', $actual['truck_pause_min'], $simulated['truck_pause_min'],
                $this->diff($actual['truck_pause_min'], $simulated['truck_pause_min'])],
            ['Средний простой самосвала', $actual['avg_truck_pause'], $simulated['avg_truck_pause'],
                $this->diff($actual['avg_truck_pause'], $simulated['avg_truck_pause'])],
            ['Простои экскаваторов (мин)', $actual['miner_idle_min'], $simulated['miner_idle_min'],
                $this->diff($actual['miner_idle_min'], $simulated['miner_idle_min'])],
            ['Средний простой экскаватора', $actual['avg_miner_idle'], $simulated['avg_miner_idle'],
                $this->diff($actual['avg_miner_idle'], $simulated['avg_miner_idle'])],
            ['Рейсов', $actual['trips_count'], $simulated['trips_count'],
                $this->diff($actual['trips_count'], $simulated['trips_count'])],
            ['Объём горной массы (м³)', $actual['total_volume'], $simulated['total_volume'],
                $this->diff($actual['total_volume'], $simulated['total_volume'])],
        ];

        $this->table($headers, $rows);

        // 5. JSON файл
        $result = [
            'config_path'  => $jsonPath,
            'shift_start'  => $config->shiftStart->format('Y-m-d H:i'),
            'shift_end'    => $config->shiftEnd->format('Y-m-d H:i'),
            'infrastructure' => [
                'miners'   => $config->miners->count(),
                'trucks'   => $config->trucks->count(),
                'zones'    => $config->zones->count(),
                'orders'   => $config->orders->count(),
            ],
            'parameters' => [
                'avg_speed'       => $config->avgSpeed,
                'avg_load_time'   => $config->avgLoadTime,
                'avg_unload_time' => $config->avgUnloadTime,
                'time_step_min'   => $config->timeStepMinutes,
                'total_ticks'     => $config->getTotalTicks(),
            ],
            'actual'     => $actual,
            'simulated'  => $simulated,
        ];

        $basename = pathinfo($jsonPath, PATHINFO_FILENAME);
        $filename = "simulation/result_{$basename}.json";
        Storage::put($filename, json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $this->info("\nРезультат сохранён: storage/app/{$filename}");

        return self::SUCCESS;
    }

    /**
     * Разница в % (зелёным = хорошо, красным = плохо — не реализовано в CLI).
     */
    private function diff($actual, $simulated): string
    {
        if ($actual == 0) return '—';
        $pct = round((($simulated - $actual) / $actual) * 100, 1);
        $sign = $pct > 0 ? '+' : '';
        return "{$sign}{$pct}%";
    }
}
