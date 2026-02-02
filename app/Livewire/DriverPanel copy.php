<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Truck;
use App\Events\TruckStatusUpdated;

class DriverPanel extends Component
{
    public ?Truck $truck = null;
    public $currentOrder = null;
    public $nextAction = '';
    public $truckId;

    protected $listeners = ['refreshDriverPanel' => '$refresh'];

    public function mount($truckId)
    {
        $this->truckId = $truckId;
        $this->truck = Truck::with('driver', 'currentOrder.miner', 'currentOrder.dump')->find($truckId);
        $this->currentOrder = $this->truck->currentOrder ?? null;
        $this->nextAction = $this->truck?->status ?? 'free';
    }

    public function driverAction()
    {
        $next = match ($this->truck->status) {
            'free'          => 'to_miner',
            'to_miner'      => 'loading',
            'loading'       => 'transporting',
            'transporting'  => 'unloading',
            'unloading'     => 'completed',
            'completed'     => 'free',
            default         => $this->truck->status,
        };

        $this->truck->status = $next;
        $this->truck->save();

        event(new TruckStatusUpdated($this->truck));

        $this->refreshDriverPanel();
    }


    public function refreshDriverPanel()
    {
        $this->truck = $this->truck->fresh();
        $this->currentOrder = $this->truck->currentOrder;
        $this->nextAction = $this->truck->status;
    }

    public function render()
    {
        return view('livewire.driver-panel')->layout('layouts.livewire', [
            'title' => 'Панель водителя',
        ]);
    }
}
