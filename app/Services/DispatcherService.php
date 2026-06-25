<?php

namespace App\Services;

use App\Models\Truck;
use App\Models\MiningOrder;
use App\Events\DispatcherNotification;
use App\Events\DriverRouteUpdated;
use Illuminate\Support\Facades\Log;

class DispatcherService
{
    protected RouteAssignmentService $assignmentService;
    protected TruckStatusService $statusService;
    protected RouteStatisticsService $statistics;

    public function __construct(
        RouteAssignmentService $assignmentService,
        TruckStatusService $statusService,
        RouteStatisticsService $statistics
    ) {
        $this->assignmentService = $assignmentService;
        $this->statusService = $statusService;
        $this->statistics = $statistics;
    }

    /**
     * Получить все грузовики с их статусами
     */
    public function getAllTrucks(): array
    {
        return Truck::with(['driver', 'currentTrip.miner', 'currentTrip.dump'])
            ->get()
            ->map(function($truck) {
                $trip = $truck->currentTrip;
                
                return [
                    'id' => $truck->id,
                    'number' => $truck->number,
                    'brand' => $truck->brand,
                    'status' => $truck->status,
                    'status_label' => \App\Domain\TruckStatus::label($truck->status),
                    'driver_id' => $truck->driver_id,
                    'driver_name' => $truck->driver?->name,
                    'fuel_level' => $truck->fuel_level,
                    'load_capacity' => $truck->load_capacity,
                    
                    'current_trip' => $trip ? [
                        'miner_name' => $trip->miner?->name_miner,
                        'dump_name' => $trip->dump?->name_dump,
                        'distance' => $trip->miningOrder?->distance_km,
                        'started_at' => $trip->started_at?->format('H:i'),
                        'duration_minutes' => $trip->started_at 
                            ? now()->diffInMinutes($trip->started_at) 
                            : 0,
                    ] : null,
                ];
            })
            ->groupBy('status')
            ->toArray();
    }

    /**
     * Получить грузовики по статусам
     */
    public function getTrucksByStatus(): array
    {
        $trucks = $this->getAllTrucks();
        
        $inWork = array_merge(
            $trucks['to_miner'] ?? [],
            $trucks['loading'] ?? [],
            $trucks['transporting'] ?? [],
            $trucks['unloading'] ?? []
        );
        
        return [
            'in_work' => $inWork,
            'free' => $trucks['free'] ?? [],
            'breakdown' => $trucks['breakdown'] ?? [],
            'maintenance' => $trucks['maintenance'] ?? [],
            'fueling' => $trucks['fueling'] ?? [],
        ];
    }

    /**
     * Переназначить грузовик на другой маршрут
     */
    public function reassignTruck(Truck $truck, int $newOrderId): array
    {
        $newOrder = MiningOrder::findOrFail($newOrderId);
        
        $result = $this->assignmentService->reassignTruck($truck, $newOrder);

        if ($result) {
            Log::info("Диспетчер переназначил грузовик {$truck->id} на маршрут {$newOrderId}");
            
            return [
                'success' => true,
                'message' => "Грузовик {$truck->number} переназначен",
            ];
        }

        return [
            'success' => false,
            'message' => 'Невозможно переназначить в текущем статусе',
        ];
    }

    /**
     * Установить поломку
     */
    public function setBreakdown(Truck $truck, string $reason = ''): array
    {
        if (in_array($truck->status, ['breakdown', 'maintenance', 'fueling'])) {
            return [
                'success' => false,
                'message' => "Грузовик уже в статусе {$truck->status}",
            ];
        }

        $this->statusService->changeStatus($truck, 'breakdown');

        Log::info("Диспетчер установил поломку для грузовика {$truck->id}", [
            'reason' => $reason,
        ]);

        return [
            'success' => true,
            'message' => "Поломка зафиксирована для {$truck->number}",
        ];
    }

    /**
     * Назначить плановые работы
     */
    public function setPlannedWork(Truck $truck, string $type): array
    {
        if (!in_array($type, ['maintenance', 'fueling'])) {
            return [
                'success' => false,
                'message' => 'Неверный тип работ',
            ];
        }

        if (!in_array($truck->status, ['free', 'breakdown'])) {
            return [
                'success' => false,
                'message' => "Нельзя назначить работы в статусе {$truck->status}",
            ];
        }

        $this->statusService->changeStatus($truck, $type);

        $typeName = $type === 'maintenance' ? 'Обслуживание' : 'Заправка';
        
        Log::info("Диспетчер назначил {$typeName} для грузовика {$truck->id}");

        return [
            'success' => true,
            'message' => "{$typeName} назначено для {$truck->number}",
        ];
    }

    /**
     * Освободить грузовик
     */
    public function setFree(Truck $truck): array
    {
        if (!in_array($truck->status, ['breakdown', 'maintenance', 'fueling'])) {
            return [
                'success' => false,
                'message' => "Нельзя освободить из статуса {$truck->status}",
            ];
        }

        $this->statusService->changeStatus($truck, 'free');

        Log::info("Диспетчер освободил грузовик {$truck->id}");

        return [
            'success' => true,
            'message' => "Грузовик {$truck->number} готов к работе",
        ];
    }

    /**
     * Статистика для панели диспетчера
     */
    public function getDashboardStats(): array
    {
        return $this->statistics->getSystemStats();
    }
}