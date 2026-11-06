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

trait WithDumps
{
    public $newDumpName;
    public $addZoneDumpId = null;
    public $newZoneName = 'Зона 1';
    public $newZoneRockId;
    public $newZoneCapacity = 10000;
    public $newZoneVolume = 0;
    public ?int $bermZoneId = null;
    public ?string $bermZoneName = null;
    public int $bermTrucksNeeded = 1;
    public ?int $bermRockId = null;
    public bool $showBermModal = false;

    public function getDumpsProperty()
    {
        return Cache::remember('master_panel.dumps', 300, function () {
            return Dump::with(['zones.rocks'])->orderBy('name_dump')->get();
        });
    }

    public function getActiveBermRequestsProperty()
    {
        return \App\Models\BermRequest::active()
            ->with(['zone', 'dump', 'rock'])
            ->orderBy('created_at')
            ->get();
    }

    public function addDump()
    {
        $this->validate(['newDumpName' => 'required|string|max:255']);
        $dump = \App\Models\Dump::create([
            'name_dump' => $this->newDumpName,
            'last_updated_by' => auth()->id() // Фиксируем автора
        ]);
        $this->reset('newDumpName');
        
        // Автоматически открываем форму создания зоны для нового отвала
        $this->addZoneDumpId = $dump->id;

        // Автозапуск оптимизации — добавлен новый отвал, нужно пересчитать маршруты
        $this->autoOptimizeRoutes('добавлении отвала');

        // Сброс кеша перегрузок + Livewire getter activeRoutes (маршруты зависят от зон)
        $this->invalidateCache(['dumps']);
        $this->invalidateLivewireCache(['activeRoutes']);

        $this->dispatch('notify', ['type' => 'success', 'message' => 'Перегрузка добавлена. Создайте зону.']);
    }

    public function deleteDump($id)
    {
        try { \App\Models\Dump::find($id)->delete(); } catch (\Exception $e) {}

        // Автозапуск оптимизации — отвал удалён, нужно пересчитать маршруты
        $this->autoOptimizeRoutes('удалении отвала');

        // Сброс кеша перегрузок + Livewire getter activeRoutes
        $this->invalidateCache(['dumps']);
        $this->invalidateLivewireCache(['activeRoutes']);
        $this->dispatch('notify', ['type' => 'info', 'message' => 'Перегрузка удалена']);
    }

    public function toggleServicePoint(int $dumpId): void
    {
        $dump = \App\Models\Dump::find($dumpId);
        if (!$dump) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Перегрузка не найдена']);
            return;
        }

        if ($dump->is_service_point) {
            // Снимаем статус
            $dump->is_service_point = false;
            $dump->save();
            $this->dispatch('notify', [
                'type' => 'info',
                'message' => "«{$dump->name_dump}» больше не сервисная точка",
            ]);
        } else {
            // Назначаем сервисной (снимает флаг с других)
            $dump->markAsServicePoint();
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => "«{$dump->name_dump}» — теперь сервисная точка. Будет использоваться как стартовая для самосвалов без истории.",
            ]);
        }

        // Сброс кеша перегрузок — сервисная точка изменилась
        $this->invalidateCache(['dumps']);
        if (isset($this->dumps)) {
            unset($this->dumps);
        }
    }

    public function toggleAddZone($dumpId)
    {
        $this->addZoneDumpId = $this->addZoneDumpId === $dumpId ? null : $dumpId;
    }

    public function addZone($dumpId)
    {
        $this->validate([
            'newZoneName' => 'required|string',
            'newZoneRockId' => 'required|exists:rocks,id',
            'newZoneCapacity' => 'required|numeric',
            'newZoneVolume' => 'required|numeric',
        ]);

        $zone = \App\Models\Zone::create([
            'dump_id' => $dumpId,
            'name_zone' => $this->newZoneName,
            'capacity' => $this->newZoneCapacity,
            'volume' => $this->newZoneVolume,
            'delivery' => true,
            'last_updated_by' => auth()->id() // Фиксируем автора
        ]);
        
        $zone->rocks()->sync([$this->newZoneRockId]);

        $this->reset(['newZoneName', 'newZoneRockId', 'newZoneCapacity', 'newZoneVolume', 'addZoneDumpId']);

        // Сброс кеша перегрузок + Livewire getter activeRoutes (зона изменилась → маршруты тоже)
        $this->invalidateCache(['dumps']);
        $this->invalidateLivewireCache(['activeRoutes']);
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Зона добавлена']);
    }

    public function updateZoneField($zoneId, $field, $value)
    {
        $zone = \App\Models\Zone::find($zoneId);
        if (!$zone) return;

        // Запоминаем старое значение delivery — нужно для выбора ZoneOpened vs ZoneClosed
        $oldDelivery = $zone->delivery;

        if ($field === 'delivery') {
            $zone->delivery = filter_var($value, FILTER_VALIDATE_BOOLEAN);
        } elseif (in_array($field, ['volume', 'capacity', 'name_zone'])) {
            $zone->$field = $value;
        } elseif ($field === 'rock_id') {
            $zone->rocks()->sync([$value]);
        }

        // Фиксируем, кто изменил зону
        $zone->last_updated_by = auth()->id();
        $zone->save();

        app(\App\Services\MiningOrderSyncService::class)->syncActiveStatusForZone($zone->id);

        // ОТПРАЛЯЕМ СИГНАЛ ВОДИТЕЛЯМ
        event(new \App\Events\RoutesUpdated());

        // === EVENT-DRIVEN (Подход B): диспатчим ZoneOpened или ZoneClosed ===
        // Process Manager (RouteAssignmentListener) через queue worker найдёт
        // ждущих драйверов и попытается им назначить маршрут.
        if ($field === 'delivery') {
            // Сохранили delivery в зоне — проверяем, изменилось ли
            $zone->refresh();
            if ($zone->delivery && !$oldDelivery) {
                // Была закрыта, теперь открыта
                try {
                    event(new \App\Events\ZoneOpened(
                        zone: $zone,
                        userId: auth()->id(),
                    ));
                } catch (\Exception $e) {
                    Log::error('updateZoneField ZoneOpened dispatch: ' . $e->getMessage());
                }
            } elseif (!$zone->delivery && $oldDelivery) {
                // Была открыта, теперь закрыта
                try {
                    event(new \App\Events\ZoneClosed(
                        zone: $zone,
                        userId: auth()->id(),
                    ));
                } catch (\Exception $e) {
                    Log::error('updateZoneField ZoneClosed dispatch: ' . $e->getMessage());
                }
            }
        }

        // Сброс кеша перегрузок + Livewire getter activeRoutes (зона изменилась)
        $this->invalidateCache(['dumps']);
        $this->invalidateLivewireCache(['activeRoutes']);
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Зона обновлена']);
    }

        public function deleteZone($zoneId)
    {
        $zone = \App\Models\Zone::find($zoneId);
        if (!$zone) return;

        // 1. Снимаем привязку зоны с маршрутами и делаем их неактивными
        \App\Models\MiningOrder::where('zone_id', $zoneId)->update([
            'zone_id' => null,
            'active' => false
        ]);

        // 2. Удаляем саму зону
        $zone->delete();

        // Сброс кеша перегрузок + Livewire getter activeRoutes
        $this->invalidateCache(['dumps']);
        $this->invalidateLivewireCache(['activeRoutes']);
        $this->dispatch('notify', ['type' => 'info', 'message' => 'Зона удалена']);
    }

    public function openBermModal(int $zoneId): void
    {
        $zone = \App\Models\Zone::with('dump', 'rocks')->find($zoneId);
        if (!$zone) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Зона не найдена']);
            return;
        }

        // Проверяем, нет ли уже активной обваловки
        $bermService = app(\App\Services\BermService::class);
        if ($bermService->isZoneUnderBerm($zoneId)) {
            $this->dispatch('notify', ['type' => 'warning', 'message' => 'На эту зону уже есть активный запрос обваловки']);
            return;
        }

        // Проверяем, что у зоны заданы породы
        if ($zone->rocks->isEmpty()) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'У зоны не заданы породы. Сначала укажите породу через панель мастера.']);
            return;
        }

        $this->bermZoneId = $zoneId;
        $this->bermZoneName = $zone->name_zone . ' (' . $zone->dump?->name_dump . ')';
        $this->bermTrucksNeeded = 1;
        // Порода берётся автоматически из зоны (первая порода)
        // — селект выбора убран, т.к. порода определяется зоной
        $this->bermRockId = $zone->rocks->first()->id;
        $this->showBermModal = true;
    }

    public function closeBermModal(): void
    {
        $this->showBermModal = false;
        $this->bermZoneId = null;
        $this->bermZoneName = null;
        $this->bermTrucksNeeded = 1;
        $this->bermRockId = null;
    }

    public function createBermRequest(): void
    {
        if (!$this->bermZoneId) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Зона не выбрана']);
            return;
        }

        if ($this->bermTrucksNeeded < 1 || $this->bermTrucksNeeded > 50) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Количество самосвалов должно быть от 1 до 50']);
            return;
        }

        try {
            $bermService = app(\App\Services\BermService::class);
            $request = $bermService->createRequest(
                $this->bermZoneId,
                $this->bermTrucksNeeded,
                $this->bermRockId,
                auth()->id()
            );

            // Проверяем результат — сколько реально назначено сейчас
            $request->refresh();
            $assigned = $request->trucks_assigned;
            $needed = $request->trucks_needed;
            $remaining = $request->remainingToAssign();

            if ($assigned > 0 && $remaining === 0) {
                // Все назначены сразу
                $this->dispatch('notify', [
                    'type' => 'success',
                    'message' => "Запрос на обваловку создан. Назначено: {$assigned}/{$needed}.",
                ]);
            } elseif ($assigned > 0) {
                // Часть назначена, часть в очереди
                $this->dispatch('notify', [
                    'type' => 'success',
                    'message' => "Запрос на обваловку создан. Назначено: {$assigned}/{$needed}, остаток: {$remaining} — будут назначены по мере освобождения самосвалов.",
                ]);
            } else {
                // Никто не назначен сейчас — это нормально!
                // Запрос создан и ждёт в очереди. Самосвалы назначатся автоматически
                // когда освободятся (через RouteAssignmentService::assignForTruck → BermService::assignTrucks)
                $freeTrucks = \App\Models\Truck::whereIn('status', ['free', 'completed'])->count();

                \Illuminate\Support\Facades\Log::info('BermRequest: создан, самосвалы в очереди', [
                    'request_id'      => $request->id,
                    'zone_id'         => $this->bermZoneId,
                    'dump_id'         => $request->dump_id,
                    'trucks_needed'   => $needed,
                    'trucks_assigned' => $assigned,
                    'free_trucks'     => $freeTrucks,
                ]);

                $this->dispatch('notify', [
                    'type' => 'success',
                    'message' => "Запрос на обваловку создан (нужно {$needed}). Самосвалы будут назначены автоматически по мере их освобождения.",
                ]);
            }

            $this->closeBermModal();

            // Сброс кеша — создан berm-запрос, dumps и activeBermRequests изменились
            $this->invalidateCache(['dumps']);
            $this->invalidateLivewireCache(['activeBermRequests', 'dumps']);

        } catch (\Exception $e) {
            Log::error('createBermRequest failed', ['error' => $e->getMessage()]);
            $this->dispatch('notify', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function cancelBermRequest(int $requestId): void
    {
        try {
            $bermService = app(\App\Services\BermService::class);
            $bermService->cancelRequest($requestId, auth()->id());

            $this->dispatch('notify', [
                'type' => 'info',
                'message' => 'Запрос на обваловку отменён. Обычные маршруты снова доступны.',
            ]);

            // Сброс кеша — berm-запрос отменён
            $this->invalidateCache(['dumps']);
            $this->invalidateLivewireCache(['activeBermRequests', 'dumps']);

        } catch (\Exception $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

}
