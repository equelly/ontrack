<?php

namespace App\Http\Controllers;

use App\Domain\TruckStatus;
use App\Models\Truck;
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
        logger()->info('Driver status request', $request->all());

        $truck = Truck::findOrFail($request->truck_id);
            if (! TruckStatus::canTransition($truck->status, $request->to)) {
        return response()->json([
            'message' => 'Не допустимый переход статуса',
        ], 409);
    }

        $service->changeStatus($truck, $request->to);

        return response()->json([
                'status'       => $truck->status,
                'statusLabel'  => TruckStatus::label($truck->status),
                'transition'   => TruckStatus::nextTransition($truck->status),
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
}
