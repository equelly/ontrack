<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;

/**
 * HealthCheck — комплексная проверка системы.
 *
 * Проверяет:
 *   1. Подключение к БД (простой SELECT)
 *   2. Подключение к Redis (ping + set/get)
 *   3. Подключение к Reverb WebSocket (HTTP ping)
 *   4. Длину очередей (если > 1000 — warning)
 *   5. Количество failed jobs за последний час
 *   6. Load Average (системный)
 *
 * Использование:
 *   php artisan health:check
 *   php artisan health:check --verbose
 *
 * Возвращает:
 *   0 — всё ок
 *   1 — есть warnings (но система работает)
 *   2 — есть errors (критично, нужно чинить)
 */
class HealthCheck extends Command
{
    protected $signature = 'health:check {--details : Показать подробную информацию}';
    protected $description = 'Проверка состояния системы: БД, Redis, Reverb, очереди, failed jobs';

    private array $results = [];
    private int $exitCode = 0;

    public function handle(): int
    {
        $this->info('=== Проверка состояния системы ===');
        $this->newLine();

        $this->checkDatabase();
        $this->checkRedis();
        $this->checkReverb();
        $this->checkQueueSize();
        $this->checkFailedJobs();
        $this->checkLoadAverage();

        $this->newLine();
        $this->info('=== Итог ===');

        $errors = array_filter($this->results, fn($r) => $r['status'] === 'error');
        $warnings = array_filter($this->results, fn($r) => $r['status'] === 'warning');

        if (!empty($errors)) {
            $this->error('❌ Ошибок: ' . count($errors));
            $this->exitCode = 2;
        } else {
            $this->info('✅ Ошибок нет');
        }

        if (!empty($warnings)) {
            $this->warn('⚠️  Предупреждений: ' . count($warnings));
            if ($this->exitCode === 0) $this->exitCode = 1;
        } else {
            $this->info('✅ Предупреждений нет');
        }

        if ($this->option('details')) {
            $this->newLine();
            $this->info('=== Подробный отчёт ===');
            foreach ($this->results as $r) {
                $this->line("  [{$r['status']}] {$r['name']}: {$r['message']}");
            }
        }

        return $this->exitCode;
    }

    /**
     * 1. Проверка БД
     */
    private function checkDatabase(): void
    {
        try {
            DB::select('SELECT 1');
            $this->addResult('ok', 'База данных', 'Подключение активно');
        } catch (\Exception $e) {
            $this->addResult('error', 'База данных', 'Ошибка: ' . $e->getMessage());
        }
    }

    /**
     * 2. Проверка Redis
     *
     * Redis::ping() возвращает разные значения в зависимости от расширения:
     *   - PhpRedis (native): строка "PONG" или boolean true
     *   - Predis:              ОБЪЕКТ \Predis\Response\Status с payload 'PONG'
     *   - PhpRedis 5+:        boolean true
     * Любое из этих значений = Redis живой.
     */
    private function checkRedis(): void
    {
        try {
            $ping = Redis::ping();

            // Проверяем ответ — может быть строка, boolean, или объект Predis\Response\Status
            if (!$this->isPingOk($ping)) {
                throw new \Exception("Неожиданный ответ: " . var_export($ping, true));
            }

            // Тест set/get
            $testKey = 'health_check_' . time();
            Redis::set($testKey, 'ok', 'EX', 10);
            $value = Redis::get($testKey);
            Redis::del($testKey);

            if ($value !== 'ok') {
                throw new \Exception("Set/get не работает (получено: " . var_export($value, true) . ")");
            }

            $this->addResult('ok', 'Redis', 'Ping + set/get работают (ответ: ' . $this->formatPing($ping) . ')');
        } catch (\Exception $e) {
            $this->addResult('error', 'Redis', 'Ошибка: ' . $e->getMessage());
        }
    }

    /**
     * Проверить, что Redis::ping() вернул корректный ответ.
     * Учитывает все варианты разных PHP Redis-расширений.
     */
    private function isPingOk($ping): bool
    {
        // Boolean true (PhpRedis 5+)
        if ($ping === true) {
            return true;
        }

        // Строка (PhpRedis старый или вручную)
        if (is_string($ping) && in_array($ping, ['PONG', '+PONG', 'OK', '1'], true)) {
            return true;
        }

        // Объект — Predis возвращает \Predis\Response\Status
        if (is_object($ping)) {
            // У Predis\Response\Status есть метод getPayload()
            if (method_exists($ping, 'getPayload')) {
                $payload = $ping->getPayload();
                return in_array($payload, ['PONG', '+PONG', 'OK'], true);
            }

            // Fallback — через __toString
            if (method_exists($ping, '__toString')) {
                $str = (string)$ping;
                return in_array($str, ['PONG', '+PONG', 'OK'], true);
            }
        }

        return false;
    }

    /**
     * Форматировать ответ Redis::ping() для вывода в лог (короткая строка).
     */
    private function formatPing($ping): string
    {
        if ($ping === true) return 'true';
        if (is_string($ping)) return "'{$ping}'";
        if (is_object($ping)) {
            if (method_exists($ping, 'getPayload')) {
                return get_class($ping) . "('{$ping->getPayload()}')";
            }
            return get_class($ping);
        }
        return var_export($ping, true);
    }

    /**
     * 3. Проверка Reverb WebSocket сервера
     */
    private function checkReverb(): void
    {
        $host = config('reverb.servers.reverb.host', config('app.url'));
        $port = config('reverb.servers.reverb.port', 8081);

        // Проверяем через HTTP ping (Reverb имеет health endpoint)
        try {
            $response = Http::timeout(3)->get("http://{$host}:{$port}/up");

            if ($response->successful()) {
                $this->addResult('ok', 'Reverb', "HTTP пинг успешен ({$host}:{$port})");
            } else {
                $this->addResult('warning', 'Reverb', "HTTP код: {$response->status()}");
            }
        } catch (\Exception $e) {
            $this->addResult('error', 'Reverb', "Не доступен на {$host}:{$port}: " . $e->getMessage());
        }
    }

    /**
     * 4. Размер очередей
     */
    private function checkQueueSize(): void
    {
        try {
            // Размеры очередей в Redis
            $defaultSize = Redis::llen('queues:default');
            $realtimeSize = Redis::llen('queues:realtime');
            $aiSize = Redis::llen('queues:ai');
            $total = $defaultSize + $realtimeSize + $aiSize;

            if ($total > 1000) {
                $this->addResult('warning', 'Очереди', "В очередях {$total} jobs — много (default={$defaultSize}, realtime={$realtimeSize}, ai={$aiSize})");
            } elseif ($total > 100) {
                $this->addResult('warning', 'Очереди', "В очередях {$total} jobs (default={$defaultSize}, realtime={$realtimeSize}, ai={$aiSize})");
            } else {
                $this->addResult('ok', 'Очереди', "В очередях {$total} jobs (default={$defaultSize}, realtime={$realtimeSize}, ai={$aiSize})");
            }
        } catch (\Exception $e) {
            $this->addResult('error', 'Очереди', 'Не удалось получить размер: ' . $e->getMessage());
        }
    }

    /**
     * 5. Failed jobs за последний час
     */
    private function checkFailedJobs(): void
    {
        try {
            // Проверяем таблицу failed_jobs
            $count = DB::table('failed_jobs')->where('failed_at', '>=', now()->subHour())->count();

            if ($count > 10) {
                $this->addResult('error', 'Failed jobs', "За последний час: {$count} (много!)");
            } elseif ($count > 0) {
                $this->addResult('warning', 'Failed jobs', "За последний час: {$count}");
            } else {
                $this->addResult('ok', 'Failed jobs', 'За последний час: 0');
            }
        } catch (\Exception $e) {
            // Таблицы может не быть — пропускаем
            $this->addResult('warning', 'Failed jobs', 'Не удалось проверить: ' . $e->getMessage());
        }
    }

    /**
     * 6. Load Average
     *
     * Пороги (для server с N CPU):
     *   - 15-min < N×1.5       — OK (норма)
     *   - 15-min ≥ N×1.5       — warning (высокая нагрузка, но не критично)
     *   - 15-min ≥ N×3         — error (критичная перегрузка, нужно чинить)
     *
     * ВАЖНО: для dev-машины (PHP+MySQL+Redis+workers+XAMPP) на 2-core CPU
     * нагрузка 3-4 на 15-min — нормальное явление. Это warning, не error.
     * Для прода — нужен VPS с 4+ cores чтобы держать load < 2.
     */
    private function checkLoadAverage(): void
    {
        if (!function_exists('sys_getloadavg')) {
            return;
        }

        $load = sys_getloadavg();
        $load1 = $load[0];
        $load5 = $load[1];
        $load15 = $load[2];
        $cpuCount = intval(trim(shell_exec('nproc') ?? '1'));

        if ($cpuCount <= 0) {
            $this->addResult('warning', 'Load Average', "1-min: {$load1} (не удалось определить CPU count)");
            return;
        }

        // Решение по 15-min (стабильное значение, не spikes)
        if ($load15 >= $cpuCount * 3) {
            // Критичная перегрузка — длительная
            $this->addResult('error', 'Load Average',
                "1-min: {$load1}, 5-min: {$load5}, 15-min: {$load15} (CPU: {$cpuCount}, ×3=" . ($cpuCount * 3) . " — критичная перегрузка!)");
        } elseif ($load15 >= $cpuCount * 1.5) {
            // Высокая нагрузка — warning
            $this->addResult('warning', 'Load Average',
                "1-min: {$load1}, 5-min: {$load5}, 15-min: {$load15} (CPU: {$cpuCount}, ×1.5=" . round($cpuCount * 1.5, 1) . " — высокая нагрузка)");
        } else {
            $this->addResult('ok', 'Load Average',
                "1-min: {$load1}, 5-min: {$load5}, 15-min: {$load15} (CPU: {$cpuCount})");
        }
    }

    private function addResult(string $status, string $name, string $message): void
    {
        $this->results[] = compact('status', 'name', 'message');

        $icon = match($status) {
            'ok' => '✅',
            'warning' => '⚠️ ',
            'error' => '❌',
        };
        $this->line("  {$icon} {$name}: {$message}");
    }
}