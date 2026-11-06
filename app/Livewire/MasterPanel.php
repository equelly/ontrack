<?php

namespace App\Livewire;

use Livewire\Component;
use App\Services\ShiftService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

// Traits по вкладкам/функциональности — каждый в app/Livewire/Traits/Master/
use App\Livewire\Traits\Master\WithAlerts;
use App\Livewire\Traits\Master\WithDashboard;
use App\Livewire\Traits\Master\WithEquipment;
use App\Livewire\Traits\Master\WithMaintenance;
use App\Livewire\Traits\Master\WithRequests;
use App\Livewire\Traits\Master\WithRocks;
use App\Livewire\Traits\Master\WithDumps;
use App\Livewire\Traits\Master\WithEvents;
use App\Livewire\Traits\Master\WithSettings;


#[Layout('components.layouts.app')]
#[Title('Панель мастера')]

class MasterPanel extends Component
{
    // Подключаем все traits — каждый содержит свои свойства и методы
    use WithAlerts;
    use WithDashboard;
    use WithEquipment;
    use WithMaintenance;
    use WithRequests;
    use WithRocks;
    use WithDumps;
    use WithEvents;
    use WithSettings;

    public $shift;

    // Активная вкладка. Обновляется через wire:click="setTab('xxx')" в кнопках.
    // render() использует её чтобы передавать в view только данные для активной вкладки,
    // а не все сразу. Снижает SQL с ~46 до ~3-20 на запрос (зависит от вкладки).
    public string $activeTab = 'dumps';

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

    public function render(ShiftService $shiftService)
    {
        // ====== Ленивый рендер по вкладкам (Вариант A) ======
        //
        // Передаём в view() только данные, нужные для текущей вкладки ($activeTab).
        // Серверный кеш (Cache::remember) в getters rocks/categories/users/allMashines/dumps
        // дополнительно убирает SQL для редко меняющихся данных.

        $data = [];

        switch ($this->activeTab) {
            case 'dashboard':
                $data['tripMetrics']         = $this->tripMetrics;
                $data['activeHauls']         = $this->activeHauls;
                $data['zoneVolumes']         = $this->zoneVolumes;
                $data['haulsSummary']        = $this->haulsSummary;
                $data['activeRoutes']        = $this->activeRoutes;
                break;

            case 'equipment':
                $data['trucks']              = $this->trucks;
                $data['miners']              = $this->miners;
                break;

            case 'maintenance':
                $data['activeServiceTasks']  = $this->activeServiceTasks;
                $data['pendingServiceTasks'] = $this->pendingServiceTasks;
                break;

            case 'requests':
                $data['mashines']            = $this->mashines;
                $data['categories']          = $this->categories;
                $data['users']               = $this->users;
                $data['allMashines']         = $this->allMashines;
                $data['ordersCount']         = $this->ordersCount;
                break;

            case 'miners':
                $data['miners']              = $this->miners;
                $data['dumps']               = $this->dumps;
                break;

            case 'rocks':
                $data['rocks']               = $this->rocks;
                break;

            case 'dumps':
            default:
                $this->activeTab = 'dumps'; // нормализуем default
                $data['dumps']               = $this->dumps;
                $data['rocks']               = $this->rocks;
                break;
        }

        return view('livewire.master-panel', $data);
    }
}