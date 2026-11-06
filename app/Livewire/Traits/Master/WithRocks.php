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

trait WithRocks
{
    public $newRockName;

    public function addRock()
    {
        $this->validate(['newRockName' => 'required|string|max:255']);
        \App\Models\Rock::create(['name_rock' => $this->newRockName]);
        $this->reset('newRockName');
        // Сброс кеша пород
        $this->invalidateCache(['rocks']);
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Порода добавлена']);
    }

    public function deleteRock($id)
    {
        try { \App\Models\Rock::find($id)->delete(); } catch (\Exception $e) {}
        $this->invalidateCache(['rocks']);
        $this->dispatch('notify', ['type' => 'info', 'message' => 'Порода удалена']);
    }

}
