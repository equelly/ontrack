<?php

namespace App\Console\Commands;

use App\Services\ServiceSchedulingService;
use Illuminate\Console\Command;

class PlanShiftService extends Command
{
    protected $signature = 'service:plan-shift 
                            {--shift-hours=12 : Длительность смены в часах}
                            {--shift-distance=200 : Сменный пробег в км}';

    protected $description = 'Спланировать обслуживание (заправка, ТО) для всех грузовиков на смену';

    public function handle(): int
    {
        $shiftHours = (int) $this->option('shift-hours');
        $shiftDistance = (int) $this->option('shift-distance');

        $this->info("Планирование обслуживания на смену ({$shiftHours}ч, {$shiftDistance}км)...");

        $service = app(ServiceSchedulingService::class);
        $result = $service->planServiceForAllTrucks($shiftHours, $shiftDistance);

        $this->info("Грузовиков проверено: {$result['total_trucks']}");
        $this->info("Заправок запланировано: {$result['fueling_planned']}");
        $this->info("ТО запланировано: {$result['maintenance_planned']}");

        if (!empty($result['details'])) {
            $this->newLine();
            $this->info('Детали:');

            foreach ($result['details'] as $detail) {
                $this->line("- {$detail['truck_number']}:");
                foreach ($detail['tasks'] as $task) {
                    $type = $task['type'] === 'fueling' ? 'Заправка' : "ТО ({$task['to_type']})";
                    $this->line("  • {$type}");
                }
            }
        }

        return self::SUCCESS;
    }
}
