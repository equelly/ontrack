<?php

namespace App\Livewire\Traits;

use App\Models\Truck;
use App\Models\Miner;
use App\Models\Dump;
use App\Models\Zone;
use App\Models\Rock;
use App\Models\TruckTrip;
use App\Models\MiningOrder;
use App\Models\TripPause;
use App\Models\SystemSetting;
use App\Models\ServicePost;
use App\Models\TruckPlannedTask;
use App\Events\DriverRouteUpdated;
use App\Events\ExcavatorNotification;
use App\Services\RouteAssignmentService;
use App\Services\RouteOptimizerService;
use App\Services\MinerStatusService;
use App\Services\ZoneStatusService;
use App\Services\ServiceSchedulingService;
use App\Services\ShiftPlanningService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;

trait WithZones
{
    /**
     * Назначить зону разгрузки уже загруженному грузовику
     */
    private function assignZoneToLoadedTruck(Truck $truck): void
    {
        $trip = $truck->trips->first();

        if (!$trip) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Нет активного рейса у грузовика']);
            return;
        }

        $zone = $this->selectedZoneId ? Zone::with('dump')->find($this->selectedZoneId) : null;

        // Определяем породу: сначала из trip->rock, потом из miner->currentRock
        $rock = $trip->rock;
        if (!$rock && $trip->miner) {
            $rock = $trip->miner->currentRock;
        }

        // Если зона не выбрана, ищем автоматически на ВСЕХ отвалах
        if (!$zone && $rock) {
            // Сначала на текущем отвале
            $zone = $this->routeService->selectZoneForRock($trip->dump_id, $rock->id);

            // Если не нашли - ищем на всех отвалах
            if (!$zone) {
                $zone = \App\Models\Zone::with('dump')
                    ->where('delivery', true)
                    ->whereRaw('volume < capacity')
                    ->whereHas('rocks', function ($q) use ($rock) { $acceptable = \App\Services\RouteAssignmentService::ROCK_FALLBACK_CHAIN[$rock->name_rock] ?? [$rock->name_rock]; $q->whereIn('rocks.name_rock', $acceptable); })
                    ->orderBy('volume', 'asc')
                    ->first();
            }
        }

        if (!$zone) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Выберите зону разгрузки']);
            return;
        }

        try {
            // Обновляем зону, породу и отвал в текущем рейсе
            $trip->update([
                'zone_id' => $zone->id,
                'dump_id' => $zone->dump_id,
                'rock_id' => $rock?->id ?? $trip->rock_id,
            ]);

            // Обновляем mining_order если есть
            if ($trip->miningOrder) {
                $trip->miningOrder->update([
                    'zone_id' => $zone->id,
                    'dump_id' => $zone->dump_id,
                    'rock_id' => $rock?->id ?? $trip->rock_id,
                ]);
            }

            // Меняем статус на "перевозка" (едет на отвал)
            $truck->update([
                'status' => Truck::STATUS_TRANSPORTING,
                'route_version' => $truck->route_version + 1,
            ]);

            // Завершаем паузу ожидания, если есть
            $waitingPause = $trip->pauses()
                ->where('type', TripPause::TYPE_WAITING_UNLOADING)
                ->whereNull('ended_at')
                ->first();

            if ($waitingPause) {
                $waitingPause->update(['ended_at' => now()]);
            }

            // Перезагружаем trip с обновлёнными данными для события
            $trip->refresh();
            $trip->load(['miner', 'dump', 'zone', 'rock']);

            // Отправляем событие водителю с актуальными данными рейса
            event(new DriverRouteUpdated($truck, $trip));

            // Отправляем уведомление диспетчеру об изменении статуса
            event(new \App\Events\DispatcherNotification(
                $truck->id,
                $truck->status,
                [
                    'zone_id' => $zone->id,
                    'zone_name' => $zone->name_zone,
                    'dump_name' => $zone->dump?->name_dump,
                    'rock_name' => $trip->rock?->name_rock,
                ]
            ));

            $this->reset(['selectedTruckId', 'selectedMinerId', 'selectedOrderId', 'selectedZoneId', 'loadedTruckRockId']);
            $this->availableOrders = [];
            $this->availableZones = [];
            $this->loadData();

            $dumpName = $zone->dump?->name_dump ?? '—';
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => "Зона назначена: {$truck->number} → {$dumpName} - {$zone->name_zone}",
            ]);

        } catch (\Exception $e) {
            Log::error('assignZoneToLoadedTruck error', ['error' => $e->getMessage()]);
            $this->dispatch('notify', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

     // 1. Мгновенное обновление UI без тяжелых запросов
    public function toggleZone(int $zoneId, bool $delivery): void
    {
        $zone = Zone::find($zoneId);
        if (!$zone) return;

        // Обновляем в базе
        $zone->update(['delivery' => $delivery]);
        
        // Быстро синхронизируем маршруты
        app(\App\Services\MiningOrderSyncService::class)->syncActiveStatusForZone($zone->id);

        // МГНОВЕННО обновляем коллекцию в памяти компонента, чтобы UI изменился без loadData()
        $this->zones = $this->zones->map(function ($z) use ($zoneId, $delivery) {
            if ($z->id == $zoneId) {
                $z->delivery = $delivery;
            }
            return $z;
        });

        // ОТПРАЛЯЕМ СИГНАЛ ВОДИТЕЛЯМ
        event(new \App\Events\RoutesUpdated());

        // Только одно уведомление
        $this->dispatch('notify', [
            'type' => 'warning',
            'message' => $delivery ? "Зона открыта. Нажмите 'Перенаправить технику'." : "Зона закрыта. Нажмите 'Перенаправить технику'.",
        ]);
    }

    public function applyZoneChanges()
    {
        try {
            // Находим ID закрытых зон, в которые ЕДУТ самосвалы
            $closedZoneIds = TruckTrip::whereNull('completed_at')
                ->whereHas('zone', function ($q) {
                    $q->where('delivery', false);
                })
                ->pluck('zone_id')
                ->unique();

            $affectedZones = Zone::whereIn('id', $closedZoneIds)->get();

            foreach ($affectedZones as $zone) {
                $this->routeService->reassignOnZoneClose($zone);
            }

            $this->loadData();

            // ОТПРАЛЯЕМ СИГНАЛ ВОДИТЕЛЯМ
            event(new \App\Events\RoutesUpdated());

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Изменения применены. Техника перенаправлена!',
            ]);

        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Ошибка при перенаправлении: ' . $e->getMessage(),
            ]);
            $this->loadData(); 
        }
    }

    /**
     * Количество перегруженных зон
     */
    public function getOverloadedZonesCountProperty(): int
    {
        return count($this->zoneStatusService->getOverloadedZones());
    }

    /**
     * Детальная информация о перегруженных зонах
     */
    public function getOverloadedZonesProperty(): array
    {
        return $this->zoneStatusService->getOverloadedZones();
    }

    /**
     * Нагрузка на все зоны
     */
    public function getZonesLoadProperty(): array
    {
        return $this->zoneStatusService->getAllZonesLoad();
    }

    /**
     * Ручная балансировка перегруженных зон
     */
    public function balanceZones(): void
    {
        try {
            $results = $this->zoneStatusService->autoBalance();

            $this->loadData();

            if (empty($results)) {
                $this->dispatch('notify', [
                    'type' => 'info',
                    'message' => 'Перегруженных зон не обнаружено',
                ]);
            } else {
                $totalRedirected = array_sum(array_column($results, 'redirected_trucks'));
                $this->dispatch('notify', [
                    'type' => 'success',
                    'message' => "Балансировка выполнена: перенаправлено {$totalRedirected} грузовиков",
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Zone balancing failed', ['error' => $e->getMessage()]);
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Ошибка балансировки: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Перенаправить грузовики с конкретной перегруженной зоны
     */
    public function redirectFromZone(int $zoneId): void
    {
        $zone = Zone::find($zoneId);

        if (!$zone) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Зона не найдена']);
            return;
        }

        try {
            $redirectedCount = $this->zoneStatusService->redirectTrucksFromOverloadedZone($zone);

            $this->loadData();

            if ($redirectedCount > 0) {
                $this->dispatch('notify', [
                    'type' => 'success',
                    'message' => "Перенаправлено {$redirectedCount} грузовиков с зоны {$zone->name_zone}",
                ]);
            } else {
                $this->dispatch('notify', [
                    'type' => 'info',
                    'message' => "Нет грузовиков для перенаправления с зоны {$zone->name_zone}",
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Redirect from zone failed', ['error' => $e->getMessage()]);
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Ошибка: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Обработчик вебсокет-события ZoneNeedsBerm.
     * Зона заполнена до предела — требуется обваловка.
     *
     * В Панели Диспетчера уведомление показываем, но решение
     * об обваловке принимает Мастер — диспетчер только информируется.
     */
    #[On('echo:dispatcher,zone.needs.berm')]
    public function onZoneNeedsBerm($event): void
    {
        $zoneName = $event['zone_name'] ?? '—';
        $dumpName = $event['dump_name'] ?? '—';
        $fillPct  = $event['fill_percent'] ?? 100;

        $this->dispatch('notify', [
            'type' => 'warning',
            'message' => "Зона «{$zoneName}» ({$dumpName}) заполнена на {$fillPct}%. Уведомите мастера о необходимости обваловки.",
        ]);

        // Перезагружаем данные — зоны могли измениться (delivery=false)
        $this->loadData();
    }

    /**
     * Обработчик вебсокет-события ZoneFillWarning.
     * Зона заполняется (80-99%) — заблаговременное предупреждение.
     *
     * В Панели Диспетчера уведомление показываем как info — диспетчер
     * может заранее перенаправлять новые самосвалы на другие зоны,
     * не дожидаясь критического переполнения.
     */
    #[On('echo:dispatcher,zone.fill.warning')]
    public function onZoneFillWarning($event): void
    {
        $zoneName = $event['zone_name'] ?? '—';
        $dumpName = $event['dump_name'] ?? '—';
        $fillPct  = $event['fill_percent'] ?? 0;
        $hours    = $event['hours_to_overflow'] ?? null;
        $severity = $event['severity'] ?? 'warning';
        $message  = $event['message'] ?? "Зона «{$zoneName}» ({$dumpName}) заполнена на {$fillPct}%.";

        // Если < 3 часов до переполнения или severity=critical — warning, иначе info
        $notifyType = ($severity === 'critical' || ($hours !== null && $hours < 3))
            ? 'warning'
            : 'info';

        $this->dispatch('notify', [
            'type' => $notifyType,
            'message' => $message,
        ]);

        // Перезагружаем данные — прогресс-бары зон могли обновиться
        $this->loadData();
    }
}
