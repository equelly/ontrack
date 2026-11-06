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

trait WithDashboard
{
    public function getTripMetricsProperty(): array
    {
        $start = $this->shift['start_time'] ?? null;
        $end   = $this->shift['end_time'] ?? null;
        if (!$start || !$end) {
            return ['total_volume' => 0, 'total_trips' => 0, 'avg_speed' => null, 'avg_distance' => null];
        }

        // Скалярные агрегаты — быстро, без загрузки строк
        $agg = TruckTrip::whereBetween('created_at', [$start, $end])
            ->whereNotNull('completed_at')
            ->selectRaw('SUM(load_volume) as total_volume, COUNT(*) as total_trips')
            ->first();

        // Для avg_speed / avg_distance нужны данные miningOrder и время перевозки —
        // подгружаем только завершённые рейсы с miningOrder (для расчёта distance/transporting)
        $trips = TruckTrip::with('miningOrder:id,distance_km')
            ->whereBetween('created_at', [$start, $end])
            ->whereNotNull('completed_at')
            ->get(['id', 'mining_order_id', 'load_volume', 'loaded_at', 'unloading_started_at', 'completed_at']);

        $totalDistance = 0;
        $totalSpeedSum = 0.0;
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

        $tripsCount = $trips->count();

        return [
            'total_volume' => (float) ($agg->total_volume ?? 0),
            'total_trips'  => (int) ($agg->total_trips ?? 0),
            'avg_speed'    => $speedCount > 0 ? round($totalSpeedSum / $speedCount, 1) : null,
            'avg_distance' => $tripsCount > 0 ? round($totalDistance / $tripsCount, 1) : null,
        ];
    }

    public function getZoneVolumesProperty()
    {
        $start = $this->shift['start_time'] ?? null;
        $end   = $this->shift['end_time'] ?? null;
        if (!$start || !$end) {
            return collect();
        }

        // Грузим агрегаты и зоны с отвалами в два запроса, связываем в PHP.
        $rows = TruckTrip::whereBetween('created_at', [$start, $end])
            ->whereNotNull('zone_id')
            ->selectRaw('zone_id, SUM(load_volume) as total_volume')
            ->groupBy('zone_id')
            ->get();

        if ($rows->isEmpty()) {
            return $rows;
        }

        $zones = Zone::with('dump:id,name_dump')
            ->whereIn('id', $rows->pluck('zone_id'))
            ->get()
            ->keyBy('id');

        return $rows->map(function ($row) use ($zones) {
            $row->setRelation('zone', $zones->get($row->zone_id));
            return $row;
        });
    }

    public function getHaulsSummaryProperty()
    {
        $start = $this->shift['start_time'] ?? null;
        $end   = $this->shift['end_time'] ?? null;
        if (!$start || !$end) {
            return collect();
        }

        $rows = TruckTrip::whereBetween('created_at', [$start, $end])
            ->whereNotNull('zone_id')
            ->selectRaw('miner_id, zone_id, rock_id, SUM(load_volume) as total_volume, COUNT(*) as trips_count')
            ->groupBy('miner_id', 'zone_id', 'rock_id')
            ->get();

        if ($rows->isEmpty()) {
            return $rows;
        }

        // Предзагружаем только нужные сущности одним запросом каждого
        $miners = Miner::whereIn('id', $rows->pluck('miner_id')->unique())->get()->keyBy('id');
        $zones  = Zone::whereIn('id', $rows->pluck('zone_id')->unique())->get()->keyBy('id');
        $rocks  = Rock::whereIn('id', $rows->pluck('rock_id')->unique())->get()->keyBy('id');

        return $rows->map(function ($row) use ($miners, $zones, $rocks) {
            $row->setRelation('miner', $miners->get($row->miner_id));
            $row->setRelation('zone',  $zones->get($row->zone_id));
            $row->setRelation('rock',  $rocks->get($row->rock_id));
            return $row;
        });
    }

    public function getActiveHaulsProperty()
    {
        return TruckTrip::whereNull('completed_at')
            ->with(['truck:id,number', 'miner:id,name_miner', 'zone.dump', 'rock:id,name_rock'])
            ->get();
    }

    public function getActiveRoutesProperty()
    {
        return MiningOrder::where('active', true)
            ->whereNotNull('zone_id')
            ->with(['miner', 'dump', 'zone', 'rock'])
            ->get();
    }

}
