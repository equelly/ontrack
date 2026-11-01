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

trait WithRoutes
{
    // =========================================
    // УПРАВЛЕНИЕ МАРШРУТАМИ (mining_orders)
    // =========================================

    public function openEditOrder(int $orderId): void
    {
        $order = MiningOrder::find($orderId);
        if ($order) {
            $this->editOrderId = $orderId;
            $this->editDumpId = $order->dump_id;
            $this->editRockId = $order->rock_id;
            $this->editActive = $order->active;

            // Загружаем расстояния от забоя до всех перегрузок из других маршрутов
            $this->editDistances = MiningOrder::where('miner_id', $order->miner_id)
                ->whereNotNull('distance_km')
                ->get()
                ->groupBy('dump_id')
                ->map(fn($group) => $group->first()->distance_km)
                ->toArray();
        }
    }

    public function closeEditOrder(): void
    {
        $this->editOrderId = null;
        $this->editDumpId = null;
        $this->editRockId = null;
        $this->editActive = null;
        $this->editDistances = [];
    }

    public function saveOrder(): void
    {
        if (!$this->editOrderId) {
            return;
        }

        $order = MiningOrder::find($this->editOrderId);
        if (!$order) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Маршрут не найден']);
            return;
        }

        // Получаем расстояние для выбранной перегрузки
        $distance = $this->editDistances[$this->editDumpId] ?? $order->distance_km;

        // === СИНХРОНИЗАЦИЯ РАССТОЯНИЯ С miner_dump_distances ===
        // Обновляем запись в distances (источник правды) — копируем в mining_orders.
        // Если меняется dump_id — нужно создать/обновить запись в distances для НОВОЙ пары.
        if ($distance !== null) {
            $distanceRecord = \App\Models\MinerDumpDistance::firstOrNew([
                'miner_id' => $order->miner_id,
                'dump_id'  => $this->editDumpId,
            ]);
            $distanceRecord->distance_km = $distance;
            $distanceRecord->save();
        }

        $order->update([
            'dump_id' => $this->editDumpId,
            'rock_id' => $this->editRockId,
            'active' => $this->editActive,
            'distance_km' => $distance, // = distance_km из miner_dump_distances (синхронизировано выше)
        ]);

        $this->loadData();
        $this->closeEditOrder();
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Маршрут обновлён']);

        // === EVENT-DRIVEN (Подход B): обновление маршрута может сделать его доступным ===
        // Например, поменяли dump_id → теперь есть запись в miner_dump_distances.
        // Process Manager попытается назначить маршрут ждущим драйверам.
        if ($order->active) {
            try {
                event(new \App\Events\MiningOrderActivated(
                    order: $order,
                    userId: auth()->id(),
                ));
            } catch (\Exception $e) {
                Log::error('saveOrder MiningOrderActivated dispatch: ' . $e->getMessage());
            }
        }
    }

    public function toggleOrderActive(int $orderId): void
    {
        $order = MiningOrder::find($orderId);
        if ($order) {
            $order->update(['active' => !$order->active]);
            $this->loadData();
            $status = $order->active ? 'активирован' : 'деактивирован';
            $this->dispatch('notify', ['type' => 'info', 'message' => "Маршрут {$status}"]);

            // === EVENT-DRIVEN (Подход B): если активировали — диспатчим событие ===
            // Process Manager (RouteAssignmentListener) через queue worker найдёт
            // всех ждущих драйверов и попытается им назначить маршрут (т.к. теперь
            // этот MiningOrder доступен).
            if ($order->active) {
                try {
                    event(new \App\Events\MiningOrderActivated(
                        order: $order,
                        userId: auth()->id(),
                    ));
                } catch (\Exception $e) {
                    Log::error('toggleOrderActive MiningOrderActivated dispatch: ' . $e->getMessage());
                }
            }
        }
    }

    public function deleteOrder(int $orderId): void
    {
        $order = MiningOrder::find($orderId);
        if (!$order) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Маршрут не найден']);
            return;
        }

        // Проверяем есть ли активные назначения
        $hasActiveTrips = TruckTrip::where('mining_order_id', $orderId)
            ->whereNull('completed_at')
            ->exists();

        if ($hasActiveTrips) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Нельзя удалить маршрут с активными назначениями']);
            return;
        }

        $order->delete();
        $this->loadData();
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Маршрут удалён']);
    }

    public function createNewOrder(): void
    {
        $this->validate([
            'newOrderMinerId'   => 'required|exists:miners,id',
            'newOrderDumpId'    => 'required|exists:dumps,id',
            'newOrderRockId'    => 'nullable|exists:rocks,id',
            'newOrderDistanceKm' => 'nullable|numeric|min:0|max:500',
            'newOrderWeight'    => 'integer|min:1|max:1000',
        ]);

        // Проверяем, нет ли уже такого маршрута
        $exists = MiningOrder::where('miner_id', $this->newOrderMinerId)
            ->where('dump_id', $this->newOrderDumpId)
            ->exists();

        if ($exists) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Маршрут «забой → отвал» уже существует. Отредактируйте существующий.',
            ]);
            return;
        }

        // === СИНХРОНИЗАЦИЯ РАССТОЯНИЯ С miner_dump_distances ===
        // mining_orders.distance_km — это КОПИЯ из miner_dump_distances.distance_km
        // для той же пары (miner_id, dump_id). Источник правды — miner_dump_distances.
        // При ручном создании — создаём/обновляем запись в distances, копируем в mining_orders.
        $distanceRecord = \App\Models\MinerDumpDistance::firstOrNew([
            'miner_id' => $this->newOrderMinerId,
            'dump_id'  => $this->newOrderDumpId,
        ]);
        $distanceRecord->distance_km = $this->newOrderDistanceKm;
        $distanceRecord->save();

        $order = MiningOrder::create([
            'miner_id'    => $this->newOrderMinerId,
            'dump_id'     => $this->newOrderDumpId,
            'rock_id'     => $this->newOrderRockId,
            'distance_km' => $this->newOrderDistanceKm, // = distance_km из miner_dump_distances
            'weight'      => $this->newOrderWeight,
            'active'      => false, // По умолчанию неактивен — пусть оптимизатор решит
            'wrr_cursor'  => 0,
        ]);

        $this->loadData();
        $this->closeCreateOrderModal();

        $minerName = $order->miner?->name_miner ?? '#'.$order->miner_id;
        $dumpName  = $order->dump?->name_dump ?? '#'.$order->dump_id;

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => "Маршрут создан: {$minerName} → {$dumpName}",
        ]);
    }

    /**
     * Открыть модальное окно создания маршрута.
     */
    public function openCreateOrderModal(): void
    {
        $this->reset(['newOrderMinerId', 'newOrderDumpId', 'newOrderRockId', 'newOrderDistanceKm']);
        $this->newOrderWeight = 100;
        $this->showCreateOrderModal = true;
    }

    /**
     * Закрыть модальное окно создания маршрута.
     */
    public function closeCreateOrderModal(): void
    {
        $this->showCreateOrderModal = false;
        $this->reset(['newOrderMinerId', 'newOrderDumpId', 'newOrderRockId', 'newOrderDistanceKm', 'newOrderWeight']);
    }

    /**
     * Сгенерировать маршруты для всех пар забой-отвал, которых ещё нет в БД.
     * Полезно при первом запуске или при добавлении нового забоя/отвала.
     */
    public function generateAllPairs(): void
    {
        $miners = Miner::where('status', Miner::STATUS_ACTIVE)->get();
        $dumps  = \App\Models\Dump::all();

        if ($miners->isEmpty() || $dumps->isEmpty()) {
            $this->dispatch('notify', [
                'type' => 'warning',
                'message' => 'Нет активных забоев или отвалов для генерации маршрутов',
            ]);
            return;
        }

        $created = 0;
        $skipped = 0;
        $updated = 0; // ← НОВОЕ: счётчик обновлённых distance_km

        foreach ($miners as $miner) {
            foreach ($dumps as $dump) {
                $order = MiningOrder::where('miner_id', $miner->id)
                    ->where('dump_id', $dump->id)
                    ->first();

                // Расстояние из таблицы miner_dump_distances (если есть)
                $distance = \App\Models\MinerDumpDistance::where('miner_id', $miner->id)
                    ->where('dump_id', $dump->id)
                    ->value('distance_km');

                if ($order) {
                    // Маршрут существует — проверяем, нужно ли обновить distance_km
                    if ($distance !== null && (float) $order->distance_km !== (float) $distance) {
                        $order->update(['distance_km' => $distance]);
                        $updated++;
                    }
                    $skipped++;
                    continue;
                }

                // Маршрута нет — создаём
                MiningOrder::create([
                    'miner_id'    => $miner->id,
                    'dump_id'     => $dump->id,
                    'rock_id'     => $miner->current_rock_id,
                    'distance_km' => $distance,
                    'weight'      => 100,
                    'active'      => false,
                    'wrr_cursor'  => 0,
                ]);
                $created++;
            }
        }

        $this->loadData();

        $message = "Создано: {$created}";
        if ($skipped > 0) {
            $message .= ", пропущено: {$skipped}";
        }
        if ($updated > 0) {
            $message .= ", обновлено расстояний: {$updated}";
        }

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => $message,
        ]);
    }

    /**
     * Получить все маршруты с информацией для управления (UI вкладка «Маршруты»).
     *
     * Для каждого маршрута:
     *   - Текущая порода забоя
     *   - Доступные зоны (с fallback-логикой)
     *   - Расстояние (сначала из MiningOrder.distance_km, потом из miner_dump_distances)
     *   - Номер раунда (если активен) — для отображения «Раунд 1» / «Раунд 2»
     */
    public function getOrdersForManagementProperty()
    {
        // Получаем активные маршруты с информацией о том, в каком раунде они были выбраны
        // (определяем по score: чем меньше score — тем раньше раунд)
        $activeRounds = \App\Models\MiningOrder::where('active', true)
            ->orderBy('miner_id')
            ->get()
            ->groupBy('miner_id')
            ->map(function ($group) {
                // Сортируем по distance_km (если есть) или по id
                return $group->sortBy('distance_km')->values();
            });

        return MiningOrder::with(['miner.currentRock', 'dump.zones.rocks'])
            ->orderBy('miner_id')
            ->orderBy('active', 'desc') // Активные первыми
            ->orderBy('weight', 'desc')
            ->get()
            ->map(function ($order) use ($activeRounds) {
                // Текущая порода в забое
                $currentRock = $order->miner?->currentRock;

                // Доступные зоны для текущей породы на перегрузке
                // Используем fallback-цепочку пород (руда → руда_S → руда_Sera)
                $availableZones = collect();
                if ($currentRock && $order->dump) {
                    $routeService = app(\App\Services\RouteAssignmentService::class);
                    $acceptableRockNames = $routeService::ROCK_FALLBACK_CHAIN[$currentRock->name_rock] ?? [$currentRock->name_rock];

                    $availableZones = $order->dump->zones->filter(function ($zone) use ($acceptableRockNames) {
                        // Зона открыта, есть место, и принимает хотя бы одну из fallback-пород
                        $zoneAccepts = $zone->rocks->whereIn('name_rock', $acceptableRockNames)->isNotEmpty();
                        return $zone->delivery
                            && $zone->volume < $zone->capacity
                            && $zoneAccepts;
                    })->map(fn($z) => [
                        'id' => $z->id,
                        'name' => $z->name_zone,
                        'fill' => $z->capacity > 0 ? round($z->volume / $z->capacity * 100) : 0,
                    ]);
                }

                // Расстояние: сначала из MiningOrder, потом из miner_dump_distances
                $distance = $order->distance_km ?? \App\Models\MinerDumpDistance::where('miner_id', $order->miner_id)
                    ->where('dump_id', $order->dump_id)
                    ->value('distance_km');

                // Номер раунда (если маршрут активен) — индекс в отсортированном списке активных маршрутов забоя
                $round = null;
                if ($order->active && isset($activeRounds[$order->miner_id])) {
                    $roundIndex = $activeRounds[$order->miner_id]->search(function ($o) use ($order) {
                        return $o->id === $order->id;
                    });
                    $round = $roundIndex !== false ? $roundIndex + 1 : null;
                }

                return (object)[
                    'id' => $order->id,
                    'miner' => $order->miner,
                    'dump' => $order->dump,
                    'current_rock' => $currentRock,
                    'distance_km' => $distance,
                    'weight' => $order->weight ?? 100,
                    'active' => $order->active,
                    'round' => $round,
                    'available_zones' => $availableZones,
                    'has_zones' => $availableZones->isNotEmpty(),
                ];
            });
    }

    /**
     * Группировка маршрутов по забоям
     */
    public function getOrdersGroupedByMinerProperty()
    {
        return $this->getOrdersForManagementProperty()
            ->groupBy(fn($o) => $o->miner?->name_miner ?? 'Без забоя');
    }

    /**
     * Оптимизировать маршруты (выбрать лучшие для каждого забоя)
     */
    public function optimizeRoutes(): void
    {
        try {
            $result = $this->optimizerService->optimize();

            $this->loadData();

            $message = sprintf(
                'Оптимизация завершена: %d активных маршрутов в %d раундах',
                $result['stats']['active_routes'],
                $result['stats']['rounds_count']
            );

            $this->dispatch('notify', ['type' => 'success', 'message' => $message]);

        } catch (\Exception $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Ошибка оптимизации: ' . $e->getMessage()]);
            Log::error('optimizeRoutes error', ['message' => $e->getMessage()]);
        }
    }

    /**
     * Активировать резервный маршрут вручную
     */
    public function activateOrder(int $orderId): void
    {
        $order = MiningOrder::find($orderId);

        if (!$order) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Маршрут не найден']);
            return;
        }

        $order->update(['active' => true]);
        $this->loadData();

        // ОТПРАЛЯЕМ СИГНАЛ ВОДИТЕЛЯМ
        event(new \App\Events\RoutesUpdated());

        $this->dispatch('notify', ['type' => 'success', 'message' => 'Маршрут активирован']);
    }

    /**
     * Деактивировать маршрут
     */
    public function deactivateOrder(int $orderId): void
    {
        $order = MiningOrder::find($orderId);

        if (!$order) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Маршрут не найден']);
            return;
        }

        $order->update(['active' => false]);
        $this->loadData();

         // ОТПРАЛЯЕМ СИГНАЛ ВОДИТЕЛЯМ
        event(new \App\Events\RoutesUpdated());

        $this->dispatch('notify', ['type' => 'info', 'message' => 'Маршрут деактивирован']);
    }

    // =========================================
    // УПРАВЛЕНИЕ РЕЖИМОМ АКТИВАЦИИ
    // =========================================

    /**
     * Получить текущий режим активации
     */
    public function getRouteModeProperty(): string
    {
        return SystemSetting::getRouteActivationMode();
    }

    /**
     * Переключить режим активации
     */
    public function toggleRouteMode(): void
    {
        $currentMode = SystemSetting::getRouteActivationMode();
        $newMode = $currentMode === 'auto' ? 'manual' : 'auto';

        SystemSetting::setRouteActivationMode($newMode);
        $this->loadData();

        $modeLabel = $newMode === 'auto' ? 'автоматический' : 'ручной';
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => "Режим изменён на {$modeLabel}"
        ]);
    }

    /**
     * Установить режим активации
     */
    public function setRouteMode(string $mode): void
    {
        if (!in_array($mode, ['auto', 'manual'])) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Неверный режим']);
            return;
        }

        SystemSetting::setRouteActivationMode($mode);
        $this->loadData();

        $modeLabel = $mode === 'auto' ? 'автоматический' : 'ручной';
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => "Установлен {$modeLabel} режим"
        ]);
    }

    // =========================================
    // УПРАВЛЕНИЕ ВЕСОМ МАРШРУТОВ
    // =========================================

    /**
     * Открыть форму редактирования веса
     */
    public function openWeightEditor(int $orderId): void
    {
        $order = MiningOrder::find($orderId);
        if ($order) {
            $this->editOrderId = $orderId;
            $this->editWeight = $order->weight ?? 100;
        }
    }

    /**
     * Сохранить вес маршрута
     */
    public function saveWeight(): void
    {
        if (!$this->editOrderId) {
            return;
        }

        $order = MiningOrder::find($this->editOrderId);
        if (!$order) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Маршрут не найден']);
            return;
        }

        $weight = max(1, min(1000, (int)$this->editWeight));
        $order->update(['weight' => $weight]);

        $this->editOrderId = null;
        $this->editWeight = null;
        $this->loadData();

        $this->dispatch('notify', ['type' => 'success', 'message' => "Вес маршрута изменён на {$weight}"]);
    }

    /**
     * Закрыть форму редактирования веса
     */
    public function closeWeightEditor(): void
    {
        $this->editOrderId = null;
        $this->editWeight = null;
    }

    /**
     * Быстрое изменение веса (+10 или -10)
     */
    public function adjustWeight(int $orderId, int $delta): void
    {
        $order = MiningOrder::find($orderId);
        if (!$order) {
            return;
        }

        $newWeight = max(1, min(1000, ($order->weight ?? 100) + $delta));
        $order->update(['weight' => $newWeight]);
        $this->loadData();
    }
}
