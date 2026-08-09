<?php

namespace App\Livewire;

use Livewire\Component;
use App\Services\ShiftService;
use App\Models\TruckTrip;
use App\Models\Truck;
use App\Models\Miner;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;


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
            $this->dispatch('notify', ['type' => 'info', 'message' => 'Забой удален']);
        } catch (\Exception $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Невозможно удалить: есть связанные данные']);
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
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Перегрузка добавлена. Создайте зону.']);
    }

    public function deleteDump($id)
    {
        try { \App\Models\Dump::find($id)->delete(); } catch (\Exception $e) {}
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