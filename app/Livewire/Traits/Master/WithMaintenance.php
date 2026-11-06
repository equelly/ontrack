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

trait WithMaintenance
{
    public function getActiveServiceTasksProperty()
    {
        return TruckPlannedTask::where('completed', false)
            ->whereNotNull('started_at')
            ->with(['truck', 'servicePost'])
            ->get();
    }

    public function getPendingServiceTasksProperty()
    {
        return TruckPlannedTask::where('completed', false)
            ->whereNull('started_at')
            ->with('truck')
            ->orderBy('queue_position')
            ->get();
    }

}
