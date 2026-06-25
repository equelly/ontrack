<?php

namespace App\Services;

use App\Models\Truck;
use App\Models\MiningOrder;
use App\Events\DispatcherNotification;
use App\Events\DriverRouteUpdated;
use App\Models\Dump;
use App\Models\Miner;
use App\Models\MinerDumpDistance;
use Illuminate\Support\Facades\DB;


class RoutePlanningService
{
    public function recalcRoutes()
    {
        // Получаем активных miners и dump с delivery = true
        $activeMiners = Miner::where('active', 1)->get();
        $activeDumps = Dump::whereHas('zones', fn($q)=>$q->where('delivery', true))->get();

        // Берём все candidate маршруты
        $candidates = MinerDumpDistance::whereIn('miner_id', $activeMiners->pluck('id'))
            ->whereIn('dump_id', $activeDumps->pluck('id'))
            ->get();

        foreach ($candidates as $cand) {
            $order = MiningOrder::updateOrCreate(
                ['miner_id' => $cand->miner_id, 'dump_id' => $cand->dump_id],
                ['score' => $cand->score, 'active' => 1]
            );
            event(new DriverRouteUpdated([
                'driver_id' => $order->truck?->driver_id ?? null,
                'route_id' => $order->id,
                'message' => 'Маршрут обновлён'
            ]));
        }
    }
}

