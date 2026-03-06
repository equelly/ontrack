<?php

namespace App\Http\Controllers;

use App\Domain\TruckStatus;
use App\Models\Truck;
use App\Models\TruckTrip;
use App\Models\Zone;
use App\Services\RouteAssignmentService;
use App\Services\TruckStatusService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;


class DriverController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Truck $truck)
    {
        if (! $truck->driver_id) {
            abort(404, 'У грузовика нет водителя');
        }

        // Текущий маршрут (если есть)
        $currentTrip = \App\Models\TruckTrip::where('truck_id', $truck->id)
            ->whereNull('completed_at')
            ->with(['miner', 'dump', 'miningOrder'])
            ->latest()
            ->first();

        // Статистика водителя
        $stats = [
            'total_trips' => \App\Models\TruckTrip::where('truck_id', $truck->id)
                ->whereNotNull('completed_at')
                ->count(),
            'today_trips' => \App\Models\TruckTrip::where('truck_id', $truck->id)
                ->whereNotNull('completed_at')
                ->whereDate('completed_at', today())
                ->count(),
            'total_volume' => \App\Models\TruckTrip::where('truck_id', $truck->id)
                ->whereNotNull('completed_at')
                ->sum('load_volume'),
            'total_distance' => \App\Models\TruckTrip::where('truck_id', $truck->id)
                ->whereNotNull('completed_at')
                ->join('miner_dump_distances', function($join) {
                    $join->on('truck_trips.miner_id', '=', 'miner_dump_distances.miner_id')
                        ->on('truck_trips.dump_id', '=', 'miner_dump_distances.dump_id');
                })
                ->sum('miner_dump_distances.distance_km'),
        ];

        return view('drivers.show', [
            'truck' => $truck,
            'currentTrip' => $currentTrip,
            'stats' => $stats,
        ]);
    }


    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function updateStatus(Request $request, TruckStatusService $service)
    {
        $request->validate([
            'truck_id' => 'required|exists:trucks,id',
            'to' => 'required|string',
        ]);

        $truck = Truck::findOrFail($request->truck_id);

        // Проверяем что это грузовик водителя
        if ((int)$truck->driver_id !== auth()->id()) {
            return response()->json([
                'message' => 'Это не ваш грузовик',
            ], 403);
        }

        if (!TruckStatus::canTransition($truck->status, $request->to)) {
            return response()->json([
                'message' => 'Недопустимый переход статуса',
            ], 409);
        }

        // Передаём контекст (причина задержки и т.д.)
        $context = $request->only(['reason', 'estimated_delay_minutes']);

        $service->changeStatus($truck, $request->to, $context);

        return response()->json([
            'status' => $truck->status,
            'statusLabel' => TruckStatus::label($truck->status),
            'transition' => TruckStatus::nextTransition($truck->status),
        ]);
    }




    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
    /**
 * Назначить маршрут грузовику (по запросу водителя)
 */
    public function assignForTruck(Request $request, RouteAssignmentService $service)
    {
        $request->validate([
            'truck_id' => 'required|exists:trucks,id',
        ]);

        $truck = Truck::findOrFail($request->truck_id);

            // Лог для диагностики
        Log::debug('Assign check', [
            'auth_id' => auth()->id(),
            'driver_id' => $truck->driver_id,
            'truck_status' => $truck->status,
        ]);

        // Проверяем что грузовик принадлежит текущему водителю
        if ((int)$truck->driver_id !== auth()->id()) {
            return response()->json([
                'message' => 'Это не ваш грузовик',
            ], 403);
        }

        // Вызываем сервис назначения маршрута
        $service->assignForTruck($truck);

        // Перезагружаем грузовик
        $truck->refresh();

        return response()->json([
            'status' => $truck->status,
            'statusLabel' => TruckStatus::label($truck->status),
            'transition' => TruckStatus::nextTransition($truck->status),
        ]);
    }
        /**
     * Получить доступные зоны для переназначения
     */
    public function availableZones(Request $request)
    {
        $truck = Truck::findOrFail($request->truck_id);
        
        $trip = TruckTrip::where('truck_id', $truck->id)
            ->whereNull('completed_at')
            ->first();

        if (!$trip || !$trip->miningOrder) {
            return response()->json(['zones' => []]);
        }

        $rockId = $trip->miningOrder->rock_id;
        $currentZoneId = $trip->zone_id;

        $zones = Zone::where('delivery', true)
            ->whereHas('rocks', fn($q) => $q->where('rocks.id', $rockId))
            ->whereRaw('volume < capacity')
            ->where('id', '!=', $currentZoneId)
            ->with('dump', 'rocks')
            ->get()
            ->map(fn($zone) => [
                'id' => $zone->id,
                'name' => $zone->name_zone,
                'dump_name' => $zone->dump?->name_dump,
                'available_capacity' => $zone->capacity - $zone->volume,
            ]);

        return response()->json(['zones' => $zones]);
    }

    /**
     * Переназначить зону (водителем)
     */
    public function reassignZone(Request $request, RouteAssignmentService $service)
    {
        $request->validate([
            'truck_id' => 'required|exists:trucks,id',
            'zone_id' => 'required|exists:zones,id',
        ]);

        $truck = Truck::findOrFail($request->truck_id);

        // Проверяем что это грузовик водителя
        if ((int)$truck->driver_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Это не ваш грузовик',
            ], 403);
        }

        $result = $service->reassignZone($truck, $request->zone_id);

        return response()->json([
            'success' => $result,
            'message' => $result ? 'Зона изменена' : 'Не удалось изменить зону',
        ]);
    }
}
