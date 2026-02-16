<?php

namespace App\Http\Controllers;

use App\Models\Truck;
use Illuminate\Http\Request;
use App\Events\DispatcherNotification;

class DriverRouteController extends Controller
{
    public function ack(Request $request)
    {
        $request->validate([
            'truck_id' => 'required|exists:trucks,id',
        ]);

        $truck = Truck::findOrFail($request->truck_id);

        // 🧠 подтверждаем маршрут
        $truck->update([
            'route_ack_version' => $truck->route_version,
        ]);

        // 🔔 уведомляем диспетчера
        event(new DispatcherNotification(
            $truck->id,
            'route_acknowledged',
            [
                'driver_id' => (int) $truck->driver_id,
                'route_version' => $truck->route_version,
            ]
        ));

        return response()->json(['ok' => true]);
    }
    public function show(Truck $truck)
    {
        return view('driver.show', [
            'truck' => $truck,
        ]);
    }
}

