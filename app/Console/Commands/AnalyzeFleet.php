<?php

namespace App\Console\Commands;

use App\Services\AnomalyDetectionService;
use Illuminate\Console\Command;

/**
 * Анализ парка самосвалов — предсказание необходимости ТО + проверка объёмов зон.
 *
 * Запуск: php artisan ai:analyze-fleet
 * Рекомендуется по cron: каждый час.
 *
 * В одном проходе:
 *   1. Анализирует тренды времени рейса каждого самосвала — если время растёт,
 *      риск износа/поломки → создаёт AiAlert типа maintenance_predict.
 *   2. Сканирует все зоны по порогу объёма — если volume < 760 м³ (2 вертушки),
 *      создаёт AiAlert типа zone_volume_action.
 */
class AnalyzeFleet extends Command
{
    protected $signature = 'ai:analyze-fleet';
    protected $description = 'Анализ парка самосвалов + проверка объёмов зон: обнаружение аномалий и предсказание необходимости ТО';

    public function handle(AnomalyDetectionService $service): int
    {
        $this->info('=== AI: Анализ парка ===');

        $result = $service->analyzeFleetMaintenance();

        $this->info("Самосвалов проверено: {$result['trucks_analyzed']}");
        $this->info("Алертов по самосвалам: {$result['truck_alerts']}");
        $this->info("Алертов по зонам (объём < 760 м³): {$result['zone_alerts']}");
        $this->info("Всего алертов: {$result['total_alerts']}");

        return self::SUCCESS;
    }
}