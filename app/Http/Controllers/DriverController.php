<?php

namespace App\Http\Controllers;

use App\Domain\TruckStatus;
use App\Models\Truck;
use App\Services\TruckStatusService;
use Illuminate\Http\Request;



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
        // Базовые проверки (важно для будущего)
        if (! $truck->driver_id) {
            abort(404, 'У грузовика нет водителя');
        }

        return view('drivers.show', [
            'truck' => $truck,
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
}
