<?php

namespace App\Livewire\Traits\Master;

use Livewire\Component;
use Livewire\Attributes\On;
use App\Services\ShiftService;
use App\Models\TruckTrip;
use App\Models\Truck;
use App\Models\Miner;
use App\Models\Rock;
use App\Models\Zone;
use App\Models\Mashine;
use App\Models\Dump;
use App\Models\Order;
use App\Models\Category;
use App\Models\User;
use App\Models\MiningOrder;
use App\Models\TruckPlannedTask;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

trait WithSettings
{
    public function getRocksProperty()
    {
        return Cache::remember('master_panel.rocks', 3600, function () {
            return Rock::orderBy('name_rock')->get();
        });
    }

    public function mount(ShiftService $shiftService)
    {
        // Загружаем только shift — все остальные данные вычисляются лениво
        // через getXxxProperty() методы при первом обращении из вьюшки.
        $this->shift = $shiftService->getCurrentShift();
    }

    public function refreshMasterData(): void
    {
        $this->loadMasterData();
    }

        protected function loadMasterData(): void
    {
        $this->shift = app(ShiftService::class)->getCurrentShift();
        $this->invalidateLivewireCache([
            'trucks', 'miners',
            'trucksSummary', 'issueSummary',
            'tripMetrics', 'zoneVolumes', 'haulsSummary',
            'activeHauls', 'activeRoutes',
            'activeServiceTasks', 'pendingServiceTasks',
            'ordersCount', 'mashines',
            'recentAlerts', 'alertFiltersActive', 'activeBermRequests',
        ]);
    }

    protected function invalidateCache(array $keys): void
    {
        foreach ($keys as $key) {
            Cache::forget("master_panel.{$key}");
            // Сброс Livewire getter cache (если свойство с таким именем существует)
            if (isset($this->{$key})) {
                unset($this->{$key});
            }
        }
    }

    protected function invalidateLivewireCache(array $props): void
    {
        foreach ($props as $prop) {
            if (isset($this->{$prop})) {
                unset($this->{$prop});
            }
        }
    }

    protected function autoOptimizeRoutes(string $trigger): void
    {
        try {
            if (\App\Models\SystemSetting::isAutoMode()) {
                $optimizer = app(\App\Services\RouteOptimizerService::class);
                $optimizer->optimize();
                \Illuminate\Support\Facades\Log::info("AutoOptimize сработал ({$trigger}) — выполнен полный пересчёт");
            } else {
                // В ручном режиме — только пересинхронизация, без пересчёта score
                app(\App\Services\MiningOrderSyncService::class)->syncAllOrders();
                \Illuminate\Support\Facades\Log::info("AutoOptimize сработал ({$trigger}) — syncAllOrders (ручной режим)");
            }

            // Назначаем маршруты свободным самосвалам
            app(\App\Services\RouteAssignmentService::class)->assignRoutesToAllFree();
            event(new \App\Events\RoutesUpdated());
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("AutoOptimize error ({$trigger}): " . $e->getMessage());
        }
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

}
