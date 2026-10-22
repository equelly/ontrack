<?php

namespace App\Console\Commands;

use App\Services\MaintenancePlannerService;
use Illuminate\Console\Command;

/**
 * Балансировка парка — анализ забоев vs самосвалов + очереди заявок.
 *
 * Запуск: php artisan maintenance:plan-balance
 * Cron: в начале каждой смены — 7:30 и 19:30.
 *
 * Создаёт AiAlert типа TYPE_BALANCE_SUGGESTION с рекомендациями:
 *   - Какие ТО/ремонты проводить сейчас (исходя из перекоса забои↔самосвалы)
 *   - Какие заявки делать в первую очередь (по приоритету + оценке времени)
 *   - Сколько свободных постов доступно
 */
class PlanMaintenanceBalance extends Command
{
    protected $signature = 'maintenance:plan-balance';
    protected $description = 'Балансировка парка: анализ забои↔самосвалы + очередь заявок → AiAlert';

    public function handle(MaintenancePlannerService $service): int
    {
        $this->info('=== Балансировка парка ===');

        $result = $service->analyzeBalance();

        if ($result) {
            $this->info('Создан/обновлён AiAlert с рекомендациями по балансировке');
        } else {
            $this->info('Нет рекомендаций — баланс в норме');
        }

        return self::SUCCESS;
    }
}