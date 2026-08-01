<?php

namespace App\Livewire;

use Livewire\Component;
use App\Services\ShiftService;
use App\Models\TruckTrip;
use App\Models\Truck;
use App\Models\Miner;

class MasterPanel extends Component
{
    public $shift;
    public $trucksSummary;
    public $tripMetrics;
    public $issueSummary;

    public function mount(ShiftService $shiftService)
    {
        $this->shift = $shiftService->getCurrentShift();

        // 1. Реальная сводка по самосвалам
        $this->trucksSummary = [
            'total' => Truck::count(),
            'active' => Truck::whereNotIn('status', ['free', 'breakdown', 'maintenance', 'fueling'])->count(),
            'broken' => Truck::where('status', 'breakdown')->count(),
        ];

        // 2. Реальная сводка по проблемам в смене
        $this->issueSummary = [
            'breakdowns' => Truck::where('status', 'breakdown')->count() + Miner::where('status', 'breakdown')->count(),
            'delays' => Truck::whereIn('status', ['delayed', 'waiting_unloading'])->count(),
            'idle' => Truck::where('status', 'free')->count(),
        ];

        // 3. Реальные метрики рейсов за текущую смену
        $trips = TruckTrip::with('miningOrder')
            ->whereBetween('created_at', [$this->shift['start_time'], $this->shift['end_time']])
            ->whereNotNull('completed_at')
            ->get();

        $totalDistance = 0;
        $totalSpeedSum = 0;
        $speedCount = 0;

        foreach ($trips as $trip) {
            $distance = $trip->miningOrder?->distance_km ?? 0;
            if ($distance > 0) {
                $totalDistance += $distance;
                $transportingHours = $trip->getTransportingHours(); 
                if ($transportingHours > 0) {
                    $totalSpeedSum += ($distance / $transportingHours);
                    $speedCount++;
                }
            }
        }

        $this->tripMetrics = [
            'total_volume' => $trips->sum('load_volume'),
            'total_trips' => $trips->count(),
            'avg_speed' => $speedCount > 0 ? round($totalSpeedSum / $speedCount, 1) : null,
            'avg_distance' => $trips->count() > 0 ? round($totalDistance / $trips->count(), 1) : null,
        ];
    }

    public function render()
    {
        // Передаем $dumps в вид
        return view('livewire.master-panel', [
            'dumps' => \App\Models\Dump::with(['zones.rocks'])->orderBy('name_dump')->get()
        ]);
    }
}