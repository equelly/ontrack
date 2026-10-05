<?php

namespace App\Listeners;

use App\Events\Contracts\TriggersRouteAssignment;
use App\Models\Truck;
use App\Services\RouteAssignmentService;
use Illuminate\Support\Facades\Log;

/**
 * RouteAssignmentListener — PROCESS MANAGER для назначения маршрутов.
 *
 * Слушает ВСЕ события которые реализуют интерфейс TriggersRouteAssignment:
 *   - LoadingCompleted       (завершение погрузки → забой освободился)
 *   - MiningOrderActivated   (диспетчер активировал MiningOrder)
 *   - ZoneOpened             (мастер открыл зону)
 *   - ZoneClosed              (мастер закрыл зону)
 *   - RockChanged            (сменили породу в забое)
 *   - BermCompleted          (завершена обваловка зоны)
 *
 * ВАЖНО: НЕ implements ShouldQueue — работает СИНХРОННО в том же процессе
 * что и event dispatch. Это надёжнее чем через queue worker (который может
 * не обрабатывать jobs правильно).
 *
 * Минус: блокирует HTTP запрос пока listener не закончит (обычно <1 сек).
 * Для нашего проекта это ОК — Reverb на localhost, assignForTruck быстрый.
 */
class RouteAssignmentListener
{

    public function __construct(
        protected RouteAssignmentService $routeService
    ) {}

    /**
     * Обработать событие (через интерфейс TriggersRouteAssignment).
     *
     * Любое событие реализующее интерфейс попадёт сюда. Полиморфизм!
     */
    public function handle(TriggersRouteAssignment $event): void
    {
        Log::info('RouteAssignmentListener: processing', [
            'event_class'     => get_class($event),
            'reason'          => $event->getReason(),
            'miner_id'        => $event->getMinerId(),
            'mining_order_id' => $event->getMiningOrderId(),
            'zone_id'         => $event->getZoneId(),
            'dump_id'         => $event->getDumpId(),
        ]);

        // Находим все грузовики в режиме поиска маршрута
        $searchingTrucks = Truck::where('is_searching_route', true)
            ->whereNotNull('driver_id')
            ->get(['id', 'driver_id', 'number', 'status']);

        if ($searchingTrucks->isEmpty()) {
            Log::debug('RouteAssignmentListener: нет драйверов в режиме ожидания', [
                'reason' => $event->getReason(),
            ]);
            return;
        }

        Log::info('RouteAssignmentListener: нашли ждущих драйверов', [
            'count' => $searchingTrucks->count(),
            'trucks' => $searchingTrucks->pluck('id')->toArray(),
            'reason' => $event->getReason(),
        ]);

        $assigned = 0;
        $stillSearching = 0;

        foreach ($searchingTrucks as $truck) {
            try {
                // Обновляем модель из БД
                $truck->refresh();

                if (!$truck->is_searching_route) {
                    Log::debug("Truck {$truck->id} больше не в режиме поиска, пропускаем");
                    continue;
                }

                if (!in_array($truck->status, ['free', 'completed', 'to_miner'])) {
                    Log::debug("Truck {$truck->id} статус {$truck->status} — не подходит для назначения, пропускаем");
                    continue;
                }

                // Пытаемся назначить маршрут
                $this->routeService->assignForTruck($truck);
                $assigned++;

                // assignForTruck при успехе уже отправил DriverRouteUpdated (broadcast)
                // Снимаем флаг поиска
                $truck->update(['is_searching_route' => false]);

                Log::info("Truck {$truck->id}: маршрут назначен автоматически", [
                    'reason' => $event->getReason(),
                ]);

            } catch (\App\Exceptions\NoRouteAvailableException $e) {
                $stillSearching++;
                Log::debug("Truck {$truck->id}: маршрут всё ещё недоступен", [
                    'reason' => $event->getReason(),
                    'block_reason' => $e->getDiagnostics()['primary_reason'] ?? 'unknown',
                ]);

            } catch (\Exception $e) {
                Log::error("RouteAssignmentListener: error for truck {$truck->id}: " . $e->getMessage(), [
                    'reason' => $event->getReason(),
                    'exception' => get_class($e),
                ]);

                $truck->update(['is_searching_route' => false]);
            }
        }

        Log::info('RouteAssignmentListener: completed', [
            'reason'         => $event->getReason(),
            'event_class'    => get_class($event),
            'total_searching'=> $searchingTrucks->count(),
            'assigned'       => $assigned,
            'still_searching'=> $stillSearching,
        ]);
    }
}
