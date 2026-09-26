<?php

namespace App\Console\Commands;

use App\Services\AnomalyDetectionService;
use Illuminate\Console\Command;

/**
 * Анализ парка самосвалов — предсказание необходимости ТО.
 *
 * Запуск: php artisan ai:analyze-fleet
 * Рекомендуется по cron: каждый час.
 *
 * Анализирует тренды времени рейса каждого самосвала.
 * Если время растёт — риск износа/поломки → создаёт AiAlert.
 */
class AnalyzeFleet extends Command
{
    protected $signature = 'ai:analyze-fleet';
    protected $description = 'Анализ парка самосвалов: обнаружение аномалий и предсказание необходимости ТО';

    public function handle(AnomalyDetectionService $service): int
    {
        $this->info('=== AI: Анализ парка ===');

        $result = $service->analyzeFleetMaintenance();

        $this->info("Самосвалов проверено: {$result['trucks_analyzed']}");
        $this->info("Алертов создано: {$result['alerts_created']}");

        return self::SUCCESS;
    }
}