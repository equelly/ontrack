<?php

namespace App\Http\Controllers;

use App\Models\Truck;
use App\Services\DistributionService;
use App\Services\DispatcherService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;


class DispatcherController extends Controller
{
    protected DistributionService $distributionService;
    protected DispatcherService $dispatcherService;

    public function __construct(
        DistributionService $distributionService,
        DispatcherService $dispatcherService
    ) {
        $this->distributionService = $distributionService;
        $this->dispatcherService = $dispatcherService;
    }

    /**
     * Главная панель диспетчера
     */
    public function index(Request $request)
    {
        // Параметры распределения
        $mode = $request->input('mode', 'balance');
        $activeZonesOnly = $request->boolean('active_zones_only', false);

        // Получаем распределение
        $distribution = $this->distributionService->distribute([
            'mode' => $mode,
            'active_zones_only' => $activeZonesOnly,
        ]);

        // Если AJAX запрос - возвращаем JSON
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json($distribution);
        }

        // Получаем грузовики по статусам
        $trucks = $this->dispatcherService->getTrucksByStatus();

        // Статистика дашборда
        $dashboardStats = $this->dispatcherService->getDashboardStats();

        return view('dispatcher.index', [
            'assignments' => $distribution['assignments'],
            'stats' => $distribution['stats'],
            'mode' => $distribution['mode'],
            'activeZonesOnly' => $distribution['active_zones_only'],
            'trucks' => $trucks,
            'dashboardStats' => $dashboardStats,
        ]);
    }

    /**
     * Переназначить маршрут
     */
    public function reassign(Request $request, Truck $truck)
    {
        $request->validate([
            'order_id' => 'required|exists:mining_orders,id',
        ]);

        $result = $this->dispatcherService->reassignTruck(
            $truck,
            $request->order_id
        );

        return response()->json($result);
    }

    /**
     * Установить поломку
     */
    public function breakdown(Request $request, Truck $truck)
    {
        $result = $this->dispatcherService->setBreakdown(
            $truck,
            $request->input('reason', '')
        );

        return response()->json($result);
    }

    /**
     * Назначить обслуживание
     */
    public function maintenance(Request $request, Truck $truck)
    {
        $result = $this->dispatcherService->setPlannedWork($truck, 'maintenance');

        return response()->json($result);
    }

    /**
     * Назначить заправку
     */
    public function fueling(Request $request, Truck $truck)
    {
        $result = $this->dispatcherService->setPlannedWork($truck, 'fueling');

        return response()->json($result);
    }

    /**
     * Освободить грузовик
     */
    public function setFree(Request $request, Truck $truck)
    {
        $result = $this->dispatcherService->setFree($truck);

        return response()->json($result);
    }

    /**
     * Получить список доступных маршрутов для переназначения
     */
    public function availableRoutes(Request $request)
    {
        $routes = $this->distributionService->getAvailableRoutes(true);

        return response()->json([
            'routes' => $routes->take(20),
        ]);
    }
}
