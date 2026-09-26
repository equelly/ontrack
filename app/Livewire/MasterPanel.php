<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Attributes\On;
use App\Services\ShiftService;
use App\Models\TruckTrip;
use App\Models\Truck;
use App\Models\Miner;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Log;

#[Layout('components.layouts.app')]
#[Title('Панель мастера')]

class MasterPanel extends Component
{
    public $shift;
    public $trucksSummary;
    public $tripMetrics;
    public $issueSummary;
    public $zoneVolumes;
    public $haulsSummary;
    public $trucks;
    public $miners;
    public $rocks;
    public $activeServiceTasks;
    public $pendingServiceTasks;
    public $categoryId = '';
    public $userId = '';
    public $mashineId = '';
    public $createdAt = '';
    public $contentSearch = '';
    public $newMinerName;
    public $newRockName;
    public $newDumpName;
    public $editingMinerId = null;
    public $addZoneDumpId = null;
    public $newZoneName = 'Зона 1';
    public $newZoneRockId;

    // === Заявки (Order) ===
    public bool $showCreateOrderModal = false;
    public bool $showOrderDetailsModal = false;
    public ?int $viewingOrderId = null;
    public ?int $newOrderMashineId = null;
    public array $newOrderSets = [];
    public string $newOrderContent = '';
    public $newOrderImage = null;
    public $viewingOrder = null;
    public ?int $editOrderCategoryId = null;
    public ?int $editingOrderId = null; // ID заявки при редактировании (null = создание)
    public $newZoneCapacity = 10000;
    public $newZoneVolume = 0;
    public $newTruckNumber;
    public $newTruckModelId;
    public $newTruckFuel;

        // Перевод названий полей для ошибок валидации
    protected $validationAttributes = [
        'newTruckNumber' => 'Номер грузовика',
        'newTruckModelId' => 'Модель',
        'newTruckFuel' => 'Топливо',
        'newMinerName' => 'Название забоя',
        'newRockName' => 'Название породы',
        'newDumpName' => 'Название перегрузки',
        'newZoneName' => 'Название зоны',
        'newZoneRockId' => 'Порода зоны',
        'newZoneCapacity' => 'Вместимость зоны',
        'newZoneVolume' => 'Текущий объем зоны',
    ];

    public function mount(ShiftService $shiftService)
    {
        $this->shift = $shiftService->getCurrentShift();
        $this->loadMasterData();
    }
    // ==========================================
    // AI АЛЕРТЫ (для виджета в шапке — как у диспетчера)
    // ==========================================

    /**
     * Количество новых (непросмотренных) алертов.
     */
    public function getNewAlertsCountProperty(): int
    {
        return \App\Models\AiAlert::new()->count();
    }

    /**
     * Активные алерты (для виджета).
     */
    public function getRecentAlertsProperty()
    {
        return \App\Models\AiAlert::active()
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();
    }

    /**
     * Подтвердить алерт.
     */
    public function acknowledgeAlert(int $alertId): void
    {
        $alert = \App\Models\AiAlert::find($alertId);
        if ($alert) {
            $alert->acknowledge(auth()->id());
        }
    }

    /**
     * Подтвердить все алерты.
     */
    public function acknowledgeAllAlerts(): void
    {
        \App\Models\AiAlert::new()->update([
            'status' => \App\Models\AiAlert::STATUS_ACKNOWLEDGED,
            'acknowledged_at' => now(),
            'acknowledged_by' => auth()->id(),
        ]);
        $this->dispatch('notify', ['type' => 'info', 'message' => 'Все алерты подтверждены']);
    }


    /**
     * Обработчик события refresh-master-data (от JS Echo-подписки).
     * Перегружает данные панели — нужно когда приходят вебсокеты
     * о заполнении зон, обваловке и т.д.
     */
    #[On('refresh-master-data')]
    public function refreshMasterData(): void
    {
        $this->loadMasterData();
    }

    /**
     * Загрузка всех данных панели. Вызывается из mount() и refreshMasterData().
     * Вынесено в отдельный метод чтобы можно было перезагружать данные
     * при получении вебсокет-событий без полного ре-инита компонента.
     */
    protected function loadMasterData(): void
    {
        $this->rocks = \App\Models\Rock::all();
        // 1. Реальная сводка по самосвалам
        $this->trucksSummary = [
            'total' => Truck::count(),
            'active' => Truck::whereNotIn('status', ['free', 'breakdown', 'maintenance', 'fueling'])->count(),
            'broken' => Truck::where('status', 'breakdown')->count(),
        ];

        // 2. Реальная сводка по проблемам в смене
        $this->issueSummary = [
            'breakdowns' => Truck::where('status', 'breakdown')->count() + Miner::where('status', 'breakdown')->count(),
            'delays' => Truck::whereIn('status', ['delayed', 'waiting_unloading'])->count(),
            'idle' => Truck::where('status', 'free')->count(),
        ];

        // 3. Реальные метрики рейсов за текущую смену
        $trips = TruckTrip::with('miningOrder')
            ->whereBetween('created_at', [$this->shift['start_time'], $this->shift['end_time']])
            ->whereNotNull('completed_at')
            ->get();

        $totalDistance = 0;
        $totalSpeedSum = 0;
        $speedCount = 0;

        foreach ($trips as $trip) {
            $distance = $trip->miningOrder?->distance_km ?? 0;
            if ($distance > 0) {
                $totalDistance += $distance;
                $transportingHours = $trip->getTransportingHours(); 
                if ($transportingHours > 0) {
                    $totalSpeedSum += ($distance / $transportingHours);
                    $speedCount++;
                }
            }
        }

        $this->tripMetrics = [
            'total_volume' => $trips->sum('load_volume'),
            'total_trips' => $trips->count(),
            'avg_speed' => $speedCount > 0 ? round($totalSpeedSum / $speedCount, 1) : null,
            'avg_distance' => $trips->count() > 0 ? round($totalDistance / $trips->count(), 1) : null,
        ];
                // 4. Объемы по зонам за смену
        $this->zoneVolumes = TruckTrip::whereBetween('created_at', [$this->shift['start_time'], $this->shift['end_time']])
            ->whereNotNull('zone_id')
            ->with('zone.dump')
            ->selectRaw('zone_id, SUM(load_volume) as total_volume')
            ->groupBy('zone_id')
            ->get();

        // 5. Сводка перевозок (Забой -> Зона) за смену
        $this->haulsSummary = TruckTrip::whereBetween('created_at', [$this->shift['start_time'], $this->shift['end_time']])
            ->whereNotNull('zone_id')
            ->with(['miner', 'zone.dump', 'rock'])
            ->selectRaw('miner_id, zone_id, rock_id, SUM(load_volume) as total_volume, COUNT(*) as trips_count')
            ->groupBy('miner_id', 'zone_id', 'rock_id')
            ->get();

        // 6. Активные перевозки в данный момент
        $this->activeHauls = TruckTrip::whereNull('completed_at')
            ->with(['truck', 'miner', 'zone.dump', 'rock'])
            ->get();

        // 7. Оборудование (Самосвалы и Экскаваторы)
        $this->trucks = \App\Models\Truck::with('truckModel')->get();
        
        $this->miners = \App\Models\Miner::with('currentRock')->get()->map(function ($miner) {
            $miner->active_trucks_count = \App\Models\TruckTrip::where('miner_id', $miner->id)
                ->whereNull('completed_at')
                ->count();
            return $miner;
        });
        // 8. Обслуживание: активные (в работе) и запланированные (в очереди)
        $this->activeServiceTasks = \App\Models\TruckPlannedTask::where('completed', false)
            ->whereNotNull('started_at')
            ->with(['truck', 'servicePost'])
            ->get();

        $this->pendingServiceTasks = \App\Models\TruckPlannedTask::where('completed', false)
            ->whereNull('started_at')
            ->with('truck')
            ->orderBy('queue_position')
            ->get();
    }

    // === Управление Забоями ===
    public function addMiner()
    {
        $this->validate(['newMinerName' => 'required|string|max:255']);
        
        // 1. Сначала создаем карточку оборудования (Mashine)
        $mashine = \App\Models\Mashine::create([
            'number' => $this->newMinerName // Название забоя будет номером карточки
        ]);

        // 2. Создаем забой и сразу привязываем к карточке
        \App\Models\Miner::create([
            'name_miner' => $this->newMinerName,
            'mashine_id' => $mashine->id
        ]);

        $this->reset('newMinerName');

        // 3. Автозапуск оптимизации — добавлен новый забой, нужно пересчитать маршруты
        $this->autoOptimizeRoutes('добавлении забоя');

        $this->dispatch('notify', ['type' => 'success', 'message' => 'Забой добавлен и связан с оборудованием']);
    }

    public function deleteMiner($id)
    {
        try {
            $miner = \App\Models\Miner::find($id);
            if ($miner) {
                // Удаляем связанную карточку оборудования, если она есть
                if ($miner->mashine_id) {
                    \App\Models\Mashine::find($miner->mashine_id)?->delete();
                }
                $miner->delete();
            }

            // Автозапуск оптимизации — забой удалён, нужно пересчитать маршруты
            $this->autoOptimizeRoutes('удалении забоя');

            $this->dispatch('notify', ['type' => 'info', 'message' => 'Забой удален']);
        } catch (\Exception $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Невозможно удалить: есть связанные данные']);
        }
    }

    /**
     * Автозапуск оптимизации маршрутов.
     * Срабатывает при добавлении/удалении забоя или отвала.
     * Если в auto-режиме — вызывает optimize() (полный пересчёт).
     * Если в manual-режиме — только syncAllOrders (без пересчёта score).
     */
    protected function autoOptimizeRoutes(string $trigger): void
    {
        try {
            if (\App\Models\SystemSetting::isAutoMode()) {
                $optimizer = app(\App\Services\RouteOptimizerService::class);
                $optimizer->optimize();
                \Illuminate\Support\Facades\Log::info("AutoOptimize сработал ({$trigger}) — выполнен полный пересчёт");
            } else {
                // В ручном режиме — только пересинхронизация, без пересчёта score
                app(\App\Services\MiningOrderSyncService::class)->syncAllOrders();
                \Illuminate\Support\Facades\Log::info("AutoOptimize сработал ({$trigger}) — syncAllOrders (ручной режим)");
            }

            // Назначаем маршруты свободным самосвалам
            app(\App\Services\RouteAssignmentService::class)->assignRoutesToAllFree();
            event(new \App\Events\RoutesUpdated());
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("AutoOptimize error ({$trigger}): " . $e->getMessage());
        }
    }
    // === Управление Грузовиками ===
    public function addTruck()
    {
        $this->validate([
            'newTruckNumber' => 'required|string|max:255',
            'newTruckModelId' => 'required|exists:truck_models,id',
            'newTruckFuel' => 'required|numeric|min:0'
        ]);

        // 1. Находим выбранную модель, чтобы взять паспортные данные
        $truckModel = \App\Models\TruckModel::find($this->newTruckModelId);

        // 2. Создаем карточку оборудования
        $mashine = \App\Models\Mashine::create([
            'number' => $this->newTruckNumber
        ]);

        // 3. Создаем самосвал
        \App\Models\Truck::create([
            'number' => $this->newTruckNumber,
            'truck_model_id' => $truckModel->id,
            'mashine_id' => $mashine->id,
            'status' => 'free',
            'load_capacity' => $truckModel->load_capacity, 
            // Берем ФАКТИЧЕСКОЕ топливо, которое ввел Мастер (не больше объема бака!)
            'fuel_level' => min($this->newTruckFuel, $truckModel->fuel_capacity ?? 9999), 
            'mileage' => 0,
            'mileage_since_fuel' => 0,
            'moto_minutes' => 0,
            'moto_minutes_since_to' => 0,
        ]);

        $this->reset(['newTruckNumber', 'newTruckModelId', 'newTruckFuel']);
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Самосвал добавлен и связан с оборудованием']);
    }
        public function deleteTruck($id)
    {
        try {
            $truck = \App\Models\Truck::find($id);
            if ($truck) {
                // Удаляем связанную карточку оборудования, если она есть
                if ($truck->mashine_id) {
                    \App\Models\Mashine::find($truck->mashine_id)?->delete();
                }
                $truck->delete();
            }
            $this->dispatch('notify', ['type' => 'info', 'message' => 'Самосвал удален']);
        } catch (\Exception $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Невозможно удалить: есть рейсы или заявки']);
        }
    }

    public function editMinerDistances($id)
    {
        // Раскрываем/скрываем панель расстояний
        $this->editingMinerId = $this->editingMinerId === $id ? null : $id;
    }

    public function saveDistance($minerId, $dumpId, $value)
    {
        // Сохраняем расстояние в таблицу miner_dump_distances
        \App\Models\MinerDumpDistance::updateOrCreate(
            ['miner_id' => $minerId, 'dump_id' => $dumpId],
            ['distance_km' => $value]
        );
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Расстояние обновлено']);
    }

    // === Управление Породами ===
    public function addRock()
    {
        $this->validate(['newRockName' => 'required|string|max:255']);
        \App\Models\Rock::create(['name_rock' => $this->newRockName]);
        $this->reset('newRockName');
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Порода добавлена']);
    }

    public function deleteRock($id)
    {
        try { \App\Models\Rock::find($id)->delete(); } catch (\Exception $e) {}
        $this->dispatch('notify', ['type' => 'info', 'message' => 'Порода удалена']);
    }

    // === Управление Перегрузками и Зонами ===
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

        $this->dispatch('notify', ['type' => 'success', 'message' => 'Перегрузка добавлена. Создайте зону.']);
    }

    public function deleteDump($id)
    {
        try { \App\Models\Dump::find($id)->delete(); } catch (\Exception $e) {}

        // Автозапуск оптимизации — отвал удалён, нужно пересчитать маршруты
        $this->autoOptimizeRoutes('удалении отвала');

        $this->dispatch('notify', ['type' => 'info', 'message' => 'Перегрузка удалена']);
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
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Зона добавлена']);
    }

    public function updateZoneField($zoneId, $field, $value)
    {
        $zone = \App\Models\Zone::find($zoneId);
        if (!$zone) return;

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
        
        $this->dumps = \App\Models\Dump::with(['zones.rocks'])->orderBy('name_dump')->get();
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

        // 3. Обновляем данные в интерфейсе
        $this->dumps = \App\Models\Dump::with(['zones.rocks'])->orderBy('name_dump')->get();
        $this->dispatch('notify', ['type' => 'info', 'message' => 'Зона удалена']);
    }

    /**
     * Обработчик вебсокет-события ZoneNeedsBerm.
     * Зона заполнена до предела — требуется обваловка
     * (предохранительный вал для безопасности горных работ).
     *
     * Событие прилетает из TruckStatusService::onUnloading() когда
     * volume зоны достиг capacity после выгрузки самосвала.
     */
    #[On('echo:master,zone.needs.berm')]
    public function onZoneNeedsBerm($event): void
    {
        $zoneName = $event['zone_name'] ?? '—';
        $dumpName = $event['dump_name'] ?? '—';
        $fillPct  = $event['fill_percent'] ?? 100;

        $this->dispatch('notify', [
            'type' => 'error',
            'message' => "⚠️ Зона «{$zoneName}» ({$dumpName}) заполнена на {$fillPct}%. Требуется обваловка!",
        ]);

        \Illuminate\Support\Facades\Log::info('MasterPanel: received ZoneNeedsBerm', [
            'zone_id' => $event['zone_id'] ?? null,
            'zone_name' => $zoneName,
        ]);

        // Обновляем данные в интерфейсе — возможно, после закрытия зоны
        // нужно перерисовать список зон
        $this->dumps = \App\Models\Dump::with(['zones.rocks'])->orderBy('name_dump')->get();
    }

    /**
     * Обработчик вебсокет-события BermProgress.
     * Прогресс обваловки зоны — обновляем UI и показываем уведомление.
     */
    #[On('echo:master,berm.progress')]
    public function onBermProgress($event): void
    {
        $zoneName = $event['zone_name'] ?? '—';
        $status   = $event['status'] ?? 'in_progress';
        $message  = $event['message'] ?? '';

        // Тип уведомления по статусу
        $type = match($status) {
            \App\Models\BermRequest::STATUS_COMPLETED => 'success',
            \App\Models\BermRequest::STATUS_CANCELLED => 'info',
            default => 'info',
        };

        $this->dispatch('notify', [
            'type' => $type,
            'message' => $message,
        ]);

        // Обновляем данные — список зон, активные обваловки
        $this->dumps = \App\Models\Dump::with(['zones.rocks'])->orderBy('name_dump')->get();
    }

    /**
     * Обработчик вебсокет-события ZoneFillWarning.
     * Зона заполняется (80-99%) — заблаговременное предупреждение.
     *
     * В отличие от ZoneNeedsBerm (когда зона уже полная), это событие
     * даёт мастеру время подготовить обваловку заранее — пока можно
     * ещё принимать самосвалы в зону.
     */
    #[On('echo:master,zone.fill.warning')]
    public function onZoneFillWarning($event): void
    {
        $zoneName = $event['zone_name'] ?? '—';
        $dumpName = $event['dump_name'] ?? '—';
        $fillPct  = $event['fill_percent'] ?? 0;
        $hours    = $event['hours_to_overflow'] ?? null;
        $severity = $event['severity'] ?? 'warning';
        $message  = $event['message'] ?? "Зона «{$zoneName}» заполнена на {$fillPct}%.";

        // Если hours_to_overflow < 3 или fill_pct >= 95 — error, иначе warning
        $notifyType = ($severity === 'critical' || ($hours !== null && $hours < 3))
            ? 'error'
            : 'warning';

        $this->dispatch('notify', [
            'type' => $notifyType,
            'message' => $message,
        ]);

        \Illuminate\Support\Facades\Log::info('MasterPanel: received ZoneFillWarning', [
            'zone_id' => $event['zone_id'] ?? null,
            'zone_name' => $zoneName,
            'fill_percent' => $fillPct,
            'hours_to_overflow' => $hours,
        ]);

        // Обновляем данные в интерфейсе — нужно перерисовать прогресс-бары заполнения зон
        $this->dumps = \App\Models\Dump::with(['zones.rocks'])->orderBy('name_dump')->get();
    }

    // ==========================================
    // ОБВАЛОВКА (BERM)
    // ==========================================

    /**
     * Свойство для модального окна создания запроса обваловки.
     */
    public ?int $bermZoneId = null;
    public ?string $bermZoneName = null;
    public int $bermTrucksNeeded = 1;
    public ?int $bermRockId = null;
    public bool $showBermModal = false;

    /**
     * Открыть модальное окно для создания запроса обваловки.
     */
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

        $this->bermZoneId = $zoneId;
        $this->bermZoneName = $zone->name_zone . ' (' . $zone->dump?->name_dump . ')';
        $this->bermTrucksNeeded = 1;
        $this->bermRockId = null;
        $this->showBermModal = true;
    }

    /**
     * Закрыть модальное окно обваловки.
     */
    public function closeBermModal(): void
    {
        $this->showBermModal = false;
        $this->bermZoneId = null;
        $this->bermZoneName = null;
        $this->bermTrucksNeeded = 1;
        $this->bermRockId = null;
    }

    /**
     * Создать запрос на обваловку.
     */
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

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => "Запрос на обваловку создан. Самосвалов нужно: {$request->trucks_needed}. Свободные самосвалы будут направлены автоматически.",
            ]);

            $this->closeBermModal();

            // Обновляем данные — возможно, зона изменилась
            $this->dumps = \App\Models\Dump::with(['zones.rocks'])->orderBy('name_dump')->get();

        } catch (\Exception $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    /**
     * Отменить запрос на обваловку (мастер вручную).
     */
    public function cancelBermRequest(int $requestId): void
    {
        try {
            $bermService = app(\App\Services\BermService::class);
            $bermService->cancelRequest($requestId, auth()->id());

            $this->dispatch('notify', [
                'type' => 'info',
                'message' => 'Запрос на обваловку отменён. Обычные маршруты снова доступны.',
            ]);

            $this->dumps = \App\Models\Dump::with(['zones.rocks'])->orderBy('name_dump')->get();

        } catch (\Exception $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    /**
     * Получить активные запросы обваловки (для отображения в UI).
     */
    public function getActiveBermRequestsProperty()
    {
        return \App\Models\BermRequest::active()
            ->with(['zone', 'dump', 'rock'])
            ->orderBy('created_at')
            ->get();
    }

    // ==========================================
    // ЗАЯВКИ (ORDER)
    // ==========================================

    /**
     * Открыть модальное окно создания заявки.
     */
    public function openCreateOrderModal(): void
    {
        $this->editingOrderId = null;
        $this->reset(['newOrderMashineId', 'newOrderSets', 'newOrderContent', 'newOrderImage']);
        $this->showCreateOrderModal = true;
    }

    /**
     * Закрыть модальное окно создания заявки.
     */
    public function closeCreateOrderModal(): void
    {
        if ($this->newOrderImage) {
            $this->newOrderImage = null;
        }
        $this->showCreateOrderModal = false;
        $this->editingOrderId = null;
        $this->reset(['newOrderMashineId', 'newOrderSets', 'newOrderContent', 'newOrderImage']);
    }

    /**
     * Открыть модалку редактирования заявки (только для автора).
     */
    public function editOrder(int $orderId): void
    {
        $order = \App\Models\Order::with(['mashine.sets'])->find($orderId);
        if (!$order) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Заявка не найдена']);
            return;
        }

        if ($order->user_id_req !== auth()->id() && auth()->user()?->role !== 'admin') {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Редактировать может только автор']);
            return;
        }

        $this->editingOrderId = $orderId;
        $this->newOrderMashineId = $order->mashine_id;
        $this->newOrderContent = $order->content ?? '';
        $this->newOrderImage = null;
        $this->newOrderSets = $order->mashine?->sets?->pluck('id')?->toArray() ?? [];

        $this->showOrderDetailsModal = false;
        $this->showCreateOrderModal = true;
    }

    /**
     * Сохранить заявку (создать или обновить).
     */
    public function saveOrder(): void
    {
        if ($this->editingOrderId) {
            $this->updateOrder();
        } else {
            $this->createOrder();
        }
    }

    /**
     * Обновить существующую заявку.
     */
    public function updateOrder(): void
    {
        $order = \App\Models\Order::find($this->editingOrderId);
        if (!$order) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Заявка не найдена']);
            return;
        }

        if ($order->user_id_req !== auth()->id() && auth()->user()?->role !== 'admin') {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Редактировать может только автор']);
            return;
        }

        $this->validate([
            'newOrderMashineId'  => 'required|exists:mashines,id',
            'newOrderContent'    => 'required|string|max:2000',
            'newOrderImage'     => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $order->update([
            'content'     => $this->newOrderContent,
            'mashine_id'  => $this->newOrderMashineId,
        ]);

        if ($this->newOrderImage) {
            $imagePath = $this->newOrderImage->store('orders', 'public');
            $order->update(['image' => $imagePath]);
        }

        // Обновляем комплектацию
        \App\Models\MashineSet::where('mashine_id', $this->newOrderMashineId)->delete();
        foreach ($this->newOrderSets as $setId) {
            \App\Models\MashineSet::firstOrCreate([
                'mashine_id' => $this->newOrderMashineId,
                'set_id'     => $setId,
            ]);
        }

        $this->dispatch('notify', [
            'type'    => 'success',
            'message' => 'Заявка обновлена',
        ]);

        $this->closeCreateOrderModal();
    }

    /**
     * Создать новую заявку.
     * Категория НЕ выбирается — автоматически "текущие".
     * Чекбоксы расходников сохраняются в mashine_sets.
     * Фото сохраняется в storage/app/public/orders/.
     */
    public function createOrder(): void
    {
        $this->validate([
            'newOrderMashineId'  => 'required|exists:mashines,id',
            'newOrderContent'    => 'required|string|max:2000',
            'newOrderSets'       => 'array',
            'newOrderSets.*'    => 'exists:sets,id',
            'newOrderImage'     => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        // Находим или создаём категорию "текущие"
        $currentCategory = \App\Models\Category::firstOrCreate(['title' => 'текущие']);

        // Сохраняем фото, если загружено
        $imagePath = null;
        if ($this->newOrderImage) {
            $imagePath = $this->newOrderImage->store('orders', 'public');
        }

        // Создаём заявку
        $order = \App\Models\Order::create([
            'content'     => $this->newOrderContent,
            'mashine_id'  => $this->newOrderMashineId,
            'category_id' => $currentCategory->id,
            'user_id_req' => auth()->id(),
            'image'       => $imagePath,
        ]);

        // Сохраняем выбранные расходники в mashine_sets
        foreach ($this->newOrderSets as $setId) {
            \App\Models\MashineSet::firstOrCreate([
                'mashine_id' => $this->newOrderMashineId,
                'set_id'     => $setId,
            ]);
        }

        $this->dispatch('notify', [
            'type'    => 'success',
            'message' => 'Заявка создана и направлена мастеру',
        ]);

        $this->closeCreateOrderModal();
    }

    /**
     * Открыть модальное окно с деталями заявки.
     */
    public function viewOrder(int $orderId): void
    {
        $this->viewingOrderId = $orderId;
        $this->viewingOrder = \App\Models\Order::with(['category', 'mashine.sets', 'user', 'userExec'])
            ->find($orderId);
        $this->editOrderCategoryId = $this->viewingOrder?->category_id;
        $this->showOrderDetailsModal = true;
    }

    /**
     * Закрыть модальное окно деталей заявки.
     */
    public function closeOrderDetailsModal(): void
    {
        $this->showOrderDetailsModal = false;
        $this->viewingOrderId = null;
        $this->viewingOrder = null;
        $this->editOrderCategoryId = null;
    }

    /**
     * Удалить заявку (soft delete).
     * Может удалить только автор заявки.
     */
    public function deleteOrder(int $orderId): void
    {
        $order = \App\Models\Order::find($orderId);
        if (!$order) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Заявка не найдена']);
            return;
        }

        // Проверка: только автор может удалить
        if ($order->user_id_req !== auth()->id() && auth()->user()?->role !== 'admin') {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Удалить заявку может только её автор']);
            return;
        }

        $order->delete();

        $this->dispatch('notify', [
            'type'    => 'info',
            'message' => 'Заявка удалена',
        ]);

        if ($this->viewingOrderId === $orderId) {
            $this->closeOrderDetailsModal();
        }
    }

    /**
     * Отметить заявку как выполненную.
     */
    public function completeOrder(int $orderId): void
    {
        $order = \App\Models\Order::find($orderId);
        if (!$order) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Заявка не найдена']);
            return;
        }

        $order->update([
            'user_exec' => auth()->id(),
        ]);

        // Сменить категорию на "выполненные"
        $doneCategory = \App\Models\Category::firstOrCreate(['title' => 'выполненные']);
        $order->update(['category_id' => $doneCategory->id]);

        $this->dispatch('notify', [
            'type'    => 'success',
            'message' => 'Заявка отмечена как выполненная',
        ]);

        if ($this->viewingOrderId === $orderId) {
            $this->viewingOrder = \App\Models\Order::with(['category', 'mashine.sets', 'user', 'userExec'])
                ->find($orderId);
        }
    }

    /**
     * Сменить категорию заявки (мастер в деталях).
     */
    public function changeOrderCategory(int $orderId): void
    {
        $order = \App\Models\Order::find($orderId);
        if (!$order) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Заявка не найдена']);
            return;
        }

        $this->validate([
            'editOrderCategoryId' => 'nullable|exists:categories,id',
        ]);

        $order->update(['category_id' => $this->editOrderCategoryId]);

        $this->dispatch('notify', [
            'type'    => 'info',
            'message' => 'Категория заявки изменена',
        ]);

        // Обновляем viewingOrder
        $this->viewingOrder = \App\Models\Order::with(['category', 'mashine.sets', 'user', 'userExec'])
            ->find($orderId);
    }

    /**
     * Переключить расходник в комплектации оборудования.
     *
     * Если позиция есть в mashine_sets — удаляем (сняли галочку: завезли/не требуется).
     * Если позиции нет — добавляем (установили галочку: требуется).
     *
     * Без toast-уведомления — чекбокс визуально переключается,
     * этого достаточно для обратной связи (модалка перекрывает toast).
     */
    public function toggleSet(int $mashineId, int $setId): void
    {
        $existing = \App\Models\MashineSet::where('mashine_id', $mashineId)
            ->where('set_id', $setId)
            ->first();

        if ($existing) {
            // Снимаем галочку — позиция не требуется
            $existing->delete();
        } else {
            // Устанавливаем галочку — позиция требуется
            \App\Models\MashineSet::create([
                'mashine_id' => $mashineId,
                'set_id'     => $setId,
            ]);
        }

        // Обновляем viewingOrder, чтобы чекбоксы перерисовались
        // Визуальное переключение (зелёный ↔ серый) — это и есть обратная связь
        if ($this->viewingOrderId) {
            $this->viewingOrder = \App\Models\Order::with(['category', 'mashine.sets', 'user', 'userExec'])
                ->find($this->viewingOrderId);
        }
    }

    public function render(ShiftService $shiftService)
    {
        // 1. Данные для выпадающих списков фильтра
        $categories = \App\Models\Category::all();
        $users = \App\Models\User::orderBy('name')->get();
        $allMashines = \App\Models\Mashine::orderBy('number')->get();
        $dumps = \App\Models\Dump::with(['zones.rocks'])->orderBy('name_dump')->get();

        // 2. Заявки с применением фильтров
        $mashines = \App\Models\Mashine::with(['orders' => function($q) {
            $q->where('content', '!=', '')
              ->when($this->contentSearch, function($q) {
                  $q->where('content', 'like', '%' . $this->contentSearch . '%');
              })
              ->when($this->categoryId, function($q) {
                  $q->where('category_id', $this->categoryId);
              })
              ->when($this->userId, function($q) {
                  $q->where('user_id', $this->userId);
              })
              ->when($this->createdAt, function($q) {
                  $q->whereDate('created_at', $this->createdAt);
              });
        }, 'sets'])
        ->when($this->mashineId, function($query) {
            $query->where('id', $this->mashineId);
        })
        ->get()
        ->filter(function($mashine) {
            return $mashine->sets->isNotEmpty() || $mashine->orders->isNotEmpty();
        });

        $ordersCount = \App\Models\Order::where('content', '!=', '')->count();

        // 3. Обновляем смену
        $this->shift = $shiftService->getCurrentShift();
        
        // 4. Активные настроенные маршруты (Забой -> Зона)
        $activeRoutes = \App\Models\MiningOrder::where('active', true)
            ->whereNotNull('zone_id')
            ->with(['miner', 'dump', 'zone', 'rock'])
            ->get();
            
        // 5. Активные перевозки (в данный момент)
        $activeHauls = \App\Models\TruckTrip::whereNull('completed_at')
            ->with(['truck', 'miner', 'zone.dump', 'rock'])
            ->get();
        
        // 6. Справочники для вкладок Мастера
        $miners = \App\Models\Miner::orderBy('name_miner')->get();
        $rocks = \App\Models\Rock::orderBy('name_rock')->get();
        
        // 7. Возвращаем вид
        return view('livewire.master-panel', compact(
            'dumps', 
            'mashines', 
            'ordersCount', 
            'categories', 
            'users', 
            'allMashines', 
            'miners', 
            'rocks', 
            'activeRoutes', 
            'activeHauls'
        ));
    }
}