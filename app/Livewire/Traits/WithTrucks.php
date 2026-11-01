<?php

namespace App\Livewire\Traits;

use App\Models\Truck;
use App\Models\Miner;
use App\Models\Dump;
use App\Models\Zone;
use App\Models\Rock;
use App\Models\TruckTrip;
use App\Models\MiningOrder;
use App\Models\TripPause;
use App\Models\SystemSetting;
use App\Models\ServicePost;
use App\Models\TruckPlannedTask;
use App\Events\DriverRouteUpdated;
use App\Events\ExcavatorNotification;
use App\Services\RouteAssignmentService;
use App\Services\RouteOptimizerService;
use App\Services\MinerStatusService;
use App\Services\ZoneStatusService;
use App\Services\ServiceSchedulingService;
use App\Services\ShiftPlanningService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;

trait WithTrucks
{
    public function getFreeTrucksCountProperty(): int
    {
        // Доступны для назначения = все кроме breakdown
        return $this->trucks->where('status', '!=', 'breakdown')->count();
    }

    public function getWorkingTrucksCountProperty(): int
    {
        // В работе = все кроме free, completed, breakdown
        return $this->trucks->whereNotIn('status', ['free', 'completed', 'breakdown'])->count();
    }

    /**
     * Диагностика причин, по которым свободные самосвалы не получили маршрут.
     *
     * Возвращает массив вида:
     *   [truck_id => ['primary_reason' => 'miner_not_working', 'summary' => [...], 'orders' => [...]]]
     *
     * Используется в шаблоне trucksTab для показа причины под бейджем статуса.
     * Кешируется на один render — вычисляется только один раз.
     */
    public function getTruckDiagnosticsProperty(): array
    {
        // Берём только свободные самосвалы (у них нет активного trip)
        $freeTrucks = $this->trucks->whereIn('status', ['free', 'completed']);

        if ($freeTrucks->isEmpty()) {
            return [];
        }

        $routeService = app(\App\Services\RouteAssignmentService::class);
        $diagnostics = [];

        foreach ($freeTrucks as $truck) {
            try {
                $diag = $routeService->diagnoseForTruck($truck);
                if (!$diag['can_assign']) {
                    $diagnostics[$truck->id] = $diag;
                }
            } catch (\Exception $e) {
                // Тихо логируем, не роняем панель диспетчера
                \Illuminate\Support\Facades\Log::error('Truck diagnostics failed', [
                    'truck_id' => $truck->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $diagnostics;
    }

    public function getFreeTrucksProperty()
    {
        // Грузовики, которым можно назначить маршрут: все кроме breakdown
        return $this->trucks->where('status', '!=', 'breakdown');
    }

    /**
     * Грузовики уже загруженные (нужна только зона разгрузки)
     */
    public function getLoadedTruckStatuses(): array
    {
        return ['waiting_unloading', 'transporting'];
    }

    /**
     * Проверить, что выбранный грузовик уже загружен
     */
    public function isSelectedTruckLoaded(): bool
    {
        if (!$this->selectedTruckId) {
            return false;
        }

        // Загружаем грузовик заново с актуальными данными
        $truck = Truck::with(['trips' => function ($q) {
            $q->whereNull('completed_at')
                ->with(['rock', 'miner.currentRock'])
                ->latest();
        }])->find($this->selectedTruckId);

        if (!$truck) {
            return false;
        }

        $isLoaded = in_array($truck->status, $this->getLoadedTruckStatuses());

        return $isLoaded;
    }

    /**
     * Получить данные загруженного грузовика для отображения
     */
    public function getLoadedTruckInfoProperty(): ?array
    {
        if (!$this->selectedTruckId) {
            return null;
        }

        $truck = Truck::with(['trips' => function ($q) {
            $q->whereNull('completed_at')
                ->with(['rock', 'miner.currentRock', 'dump'])
                ->latest();
        }])->find($this->selectedTruckId);

        if (!$truck || !in_array($truck->status, $this->getLoadedTruckStatuses())) {
            return null;
        }

        $trip = $truck->trips->first();
        if (!$trip) {
            return null;
        }

        // Определяем породу
        $rock = $trip->rock;
        if (!$rock && $trip->miner) {
            $rock = $trip->miner->currentRock;
        }

        return [
            'truck' => $truck,
            'trip' => $trip,
            'rock' => $rock,
            'rock_id' => $rock?->id,
            'miner_name' => $trip->miner?->name_miner,
            'rock_name' => $rock?->name_rock,
            'load_volume' => $trip->load_volume ?? $truck->current_load,
            'dump_id' => $trip->dump_id,
        ];
    }

    #[On('echo:dispatcher,.truck-updated')]
    public function onTruckUpdated(): void
    {
        $this->loadData();
    }

    // =========================================
    // ПРИНУДИТЕЛЬНАЯ СМЕНА СТАТУСА
    // =========================================

    public function openForceStatusModal(int $truckId): void
    {
        $this->forceStatusTruckId = $truckId;
        $this->forceStatusNew = null;
    }

    public function closeForceStatusModal(): void
    {
        $this->forceStatusTruckId = null;
        $this->forceStatusNew = null;
    }

    public function forceChangeStatus(): void
    {
        if (!$this->forceStatusTruckId || !$this->forceStatusNew) {
            return;
        }

        $truck = Truck::find($this->forceStatusTruckId);
        if (!$truck) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Самосвал не найден']);
            return;
        }

        $oldStatus = $truck->status;

        // Если переводим в свободный - завершаем текущий рейс
        if ($this->forceStatusNew === 'free') {
            $activeTrip = $truck->trips->first();
            if ($activeTrip) {
                $activeTrip->update(['completed_at' => now()]);
            }
        }

        $truck->update(['status' => $this->forceStatusNew]);

        Log::warning('DISPATCHER FORCE STATUS CHANGE', [
            'truck_id' => $truck->id,
            'truck_number' => $truck->number,
            'old_status' => $oldStatus,
            'new_status' => $this->forceStatusNew,
        ]);

        $this->closeForceStatusModal();
        $this->loadData();

        $this->dispatch('notify', [
            'type' => 'warning',
            'message' => "Статус {$truck->number} изменён: {$oldStatus} → {$this->forceStatusNew}",
        ]);
    }

    public function getAvailableStatusesProperty(): array
    {
        return [
            'free' => 'В отстое',
            'completed' => 'Ожидает назначения',
            'to_miner' => 'К забою',
            'loading' => 'Погрузка',
            'transporting' => 'Перевозка',
            'unloading' => 'Разгрузка',
            'breakdown' => 'Поломка',
            'delayed' => 'Задержка',
        ];
    }
}
