<?php

namespace App\Livewire;

use Livewire\Component;
use App\Services\ShiftService;
use App\Models\TruckTrip;
use Illuminate\Support\Facades\Auth;

class ShiftStatistics extends Component
{
    public function render(ShiftService $shiftService)
    {
        $shift = $shiftService->getCurrentShift();
        $position = Auth::user()->position ?? null;

        $tripsCount = 0;
        $totalVolume = 0;
        $totalDistance = 0;
        $totalEmptyRun = 0;
        $avgSpeed = 0;

        // Базовый запрос: рейсы за время текущей смены (используем created_at)
        $query = TruckTrip::whereBetween('created_at', [$shift['start_time'], $shift['end_time']])
            ->whereNotNull('started_at')
            ->whereNotNull('completed_at');

        if ($position === 'driver') {
            // Для водителя: фильтруем по driver_id
            $query->where('driver_id', Auth::id());

            $tripsCount = $query->count();
            $totalVolume = $query->sum('load_volume');
            $totalDistance = $query->sum('distance_km');
            $totalEmptyRun = $query->sum('empty_run_km');

        } elseif ($position === 'excavator_operator') {
            // Для экскаваторщика: фильтруем по miner_id (привязка к забою)
            $userMinerId = Auth::user()->miner_id;
            if ($userMinerId) {
                $query->where('miner_id', $userMinerId);
                $tripsCount = $query->count();
                $totalVolume = $query->sum('load_volume');
                $totalDistance = $query->sum('distance_km');
                $totalEmptyRun = $query->sum('empty_run_km');
            }
        } else {
            // Для диспетчера и админа: считаем всё за смену
            $tripsCount = $query->count();
            $totalVolume = $query->sum('load_volume');
            $totalDistance = $query->sum('distance_km');
            $totalEmptyRun = $query->sum('empty_run_km');
        }

        // Коэффициент эффективности: отношение гружёного пробега к общему
        // (гружёный + холостой). Чем выше — тем лучше.
        $totalAll = $totalDistance + $totalEmptyRun;
        $efficiency = $totalAll > 0
            ? round(($totalDistance / $totalAll) * 100, 1)
            : 0;

        return view('livewire.shift-statistics', [
            'tripsCount'     => $tripsCount,
            'totalVolume'    => $totalVolume,
            'totalDistance'  => $totalDistance,
            'totalEmptyRun'  => $totalEmptyRun,
            'efficiency'     => $efficiency,
            'avgSpeed'       => $this->calculateAvgSpeed($shift),
        ]);
    }
    
    private function calculateAvgSpeed(array $shift): float
    {
        $trips = TruckTrip::whereBetween('created_at', [$shift['start_time'], $shift['end_time']])
            ->whereNotNull('started_at')
            ->whereNotNull('completed_at')
            ->get();
            
        if ($trips->isEmpty()) {
            return 0;
        }
        
        $totalDistance = $trips->sum('distance_km');
        $totalTime = $trips->sum(function($trip) {
            return $trip->started_at->diffInMinutes($trip->completed_at);
        });
        
        return $totalTime > 0 
            ? round($totalDistance / ($totalTime / 60), 1) // km/h
            : 0;
    }
}