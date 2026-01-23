<?php

namespace App\Livewire;

use App\Models\MiningOrder;
use Livewire\Component;
use Livewire\Attributes\Url;
use App\Models\Truck;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;


class DriverPanel extends Component
{
    #[Url]
    public $truckId;  // ← Берет из URL автоматически!
    
    public $truck;
    public $currentOrder;
    public $nextAction = 'Ожидание задания';

    public function mount($truckId = null)
{
    $this->truckId = $truckId;
    if ($this->truckId) {
        $this->loadTruck(); // ← АВТОЗАГРУЗКА при входе!
    }
}

    public function updatedTruckId()
    {
        if ($this->truckId) {
            $this->loadTruck();
        }
    }
public function loadTruck()
{
    $this->truck = Truck::with('driver')->findOrFail($this->truckId)->fresh();
    $this->currentOrder = MiningOrder::where('truck_id', $this->truckId)
        ->where('active', true)
        ->with(['miner', 'dump'])
        ->first();
    $this->setNextAction();
}


    public function loadCurrentOrder()
    {
        $this->currentOrder = $this->truck->currentOrder;
        $this->setNextAction();
    }

public function setNextAction(): void
{
    $status = $this->truck->status ?? 'free';

    // ✅ Логика статусов:
    if ($status === 'free') {
        $this->nextAction = '🟡 Получить назначение';
        return;
    }
    
    if ($status === 'completed') {
        $this->nextAction = '🟡 Рейс завершён, ждём новое назначение';
        return;
    }
    
    if (!$this->currentOrder) {
        $this->nextAction = '❌ Нет активного маршрута';
        return;
    }

    $actions = [
        'to_miner'    => ' Движение к забою',
        'loading'     => ' Погрузка',
        'transporting'=> ' К месту разргрузки',
        'unloading'   => ' Разгрузка',
        'maintenance' => '🔧 Обслуживание',
        'fueling'     => '⛽ Заправка', 
        'breakdown'   => 'Неисправность',
    ];

    $this->nextAction = $actions[$status] ?? '❓ Неизвестный статус';
}
public function markAs($status)
{
    $this->validateOnly('status', [
        'status' => 'required|in:free,to_miner,loading,transporting,unloading,completed'
    ]);
    
    $this->truck->update(['status' => $status]);
    
    // 🔥 🔥 🔥 ГЛАВНОЕ!
    if ($status === 'completed') {
        Cache::put('realtime_notification', [
            'message' => "🚛 {$this->truck->number} завершил рейс", 
            'truck_id' => $this->truck->id  // ← ЭТО КРИТИЧНО!
        ], 30);
        
        Log::info('✅ Cache ПОСТАВЛЕН!', ['truck_id' => $this->truck->id]);
    }
    
    $this->loadTruck();
}



    public function driverAction(): void
    {
        $status = $this->truck->status ?? 'free';
        
        if (in_array($status, ['free', 'to_miner', 'loading', 'transporting', 'unloading', 'completed'])) {
            $this->handleRouteCycle($status);
            return;
        }
        
        if (in_array($status, ['maintenance', 'fueling', 'breakdown'])) {
            $this->truck->markAs('free');
            $this->loadTruck();
            $this->setNextAction();

            // 🔥 ДЛЯ ВСЕХ: водитель + диспетчер
            $message = "✅ {$this->truck->number}: {$this->nextAction}";
            Cache::put('realtime_notification', $message, 30);  // ← ГЛАВНЫЙ канал
            
            session()->flash('success', 'Грузовик восстановлен и готов к рейсам!');
            return;
        }
    }


private function handleRouteCycle($status): void
{
    switch ($status) {
        case 'free':
            $this->truck->markAs('to_miner');
            break;
        case 'to_miner':
            $this->truck->markAs('loading');
            break;
        case 'loading':
            $this->truck->markAs('transporting');
            break;
        case 'transporting':
            $this->truck->markAs('unloading');
            break;
        case 'unloading':
            if ($this->currentOrder) {
                $this->currentOrder->update(['truck_id' => null, 'operator_id' => null]);
            }
            
            $this->truck->markAs('completed');
            
            // 🔥 ЭТО НОВОЕ!
            Cache::put('global_truck_completed', $this->truck->id, 10);
            
            $this->loadTruck();
            break;

        
    }
    
    $this->loadTruck();
    $this->setNextAction();
    
    $message = "🚛 {$this->truck->number} → {$this->nextAction}";
    Cache::put('realtime_notification', $message, 30); // Только message!
}




// метод выхода из режимов обслуживания, заправки и ремонта
public function setInService(): void
{
    if (!in_array($this->truck->status, ['maintenance', 'fueling', 'breakdown'])) {
        session()->flash('error', 'Статус можно менять только после обслуживания!');
        return;
    }
    
    $this->truck->update(['status' => 'in_service']);
    
    Cache::put('realtime_notification', "✅ {$this->truck->number} готов к работе!", 30);
    $this->loadTruck();
    
    session()->flash('success', 'Грузовик готов к рейсам!');
}

// функция неисправности
public function reportBreakdown(): void
{
    $this->truck->update(['status' => 'breakdown']);
    
    Cache::put('realtime_notification', "🚛 {$this->truck->number}: ⚠️ НЕИСПРАВНОСТЬ! Срочно!", 60);
    $this->loadTruck();
    
    session()->flash('warning', 'Неисправность сообщена диспетчеру!');
}



    public function checkRealtimeUpdates()
    {
        $this->loadTruck();
        
        // 🔥 Читаем СВОЙ персональный канал
        $personalNotification = Cache::get("driver_notification_{$this->truckId}");
        if ($personalNotification) {
            session()->flash('realtime', $personalNotification);
            Cache::forget("driver_notification_{$this->truckId}");
        }
    }



    public function render()
    {
        
        
        return view('livewire.driver-panel');
    }



}
