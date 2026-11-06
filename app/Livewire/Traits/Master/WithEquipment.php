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

trait WithEquipment
{
    public $newMinerName;
    public $newTruckNumber;
    public $newTruckModelId;
    public $newTruckFuel;
    public $editingMinerId = null;

    public function getTrucksProperty()
    {
        return Truck::with('truckModel')->get();
    }

    public function getMinersProperty()
    {
        // Один запрос: для каждого miner_id считаем активные рейсы
        $activeCounts = TruckTrip::whereNull('completed_at')
            ->selectRaw('miner_id, COUNT(*) as cnt')
            ->groupBy('miner_id')
            ->pluck('cnt', 'miner_id');

        return Miner::with('currentRock')
            ->orderBy('name_miner')
            ->get()
            ->map(function ($miner) use ($activeCounts) {
                $miner->active_trucks_count = $activeCounts[$miner->id] ?? 0;
                return $miner;
            });
    }

    public function getTrucksSummaryProperty(): array
    {
        $row = Truck::selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN status NOT IN ('free','breakdown','maintenance','fueling') THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN status = 'breakdown' THEN 1 ELSE 0 END) as broken
        ")->first();

        return [
            'total'  => (int) $row->total,
            'active' => (int) $row->active,
            'broken' => (int) $row->broken,
        ];
    }

    public function getIssueSummaryProperty(): array
    {
        $truckRow = Truck::selectRaw("
            SUM(CASE WHEN status = 'breakdown' THEN 1 ELSE 0 END) as broken,
            SUM(CASE WHEN status IN ('delayed','waiting_unloading') THEN 1 ELSE 0 END) as delays,
            SUM(CASE WHEN status = 'free' THEN 1 ELSE 0 END) as idle
        ")->first();

        $minersBroken = Miner::where('status', 'breakdown')->count();

        return [
            'breakdowns' => (int) $truckRow->broken + $minersBroken,
            'delays'     => (int) $truckRow->delays,
            'idle'       => (int) $truckRow->idle,
        ];
    }

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

        // Сброс кеша: серверный (all_mashines) + Livewire getters (miners, activeRoutes)
        $this->invalidateCache(['all_mashines']);
        $this->invalidateLivewireCache(['miners', 'activeRoutes']);

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

            // Сброс кеша карточек оборудования + Livewire getters для забоев и маршрутов
            $this->invalidateCache(['all_mashines']);
            $this->invalidateLivewireCache(['miners', 'activeRoutes']);

            $this->dispatch('notify', ['type' => 'info', 'message' => 'Забой удален']);
        } catch (\Exception $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Невозможно удалить: есть связанные данные']);
        }
    }

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

        // Сброс кеша карточек оборудования + Livewire getter trucks
        $this->invalidateCache(['all_mashines']);
        $this->invalidateLivewireCache(['trucks']);

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
            // Сброс кеша карточек оборудования + Livewire getter trucks
            $this->invalidateCache(['all_mashines']);
            $this->invalidateLivewireCache(['trucks']);
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

}
