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

    public function updatedTruckId()
    {
        if ($this->truckId) {
            $this->loadTruck();
        }
    }

    public function loadTruck()
{
    $this->truck = Truck::with('driver')->findOrFail($this->truckId);
    
    // 🔥 ПРЯМАЯ загрузка АКТИВНОГО заказа!
    $this->currentOrder = MiningOrder::where('truck_id', $this->truckId)
                                   ->where('active', true)
                                   ->with(['miner', 'dump'])
                                   ->first();
    
    Log::info('DriverPanel заказ', [
        'truck_id' => $this->truckId,
        'order_found' => $this->currentOrder ? true : false
    ]);
    
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

    if (!$this->currentOrder && $status === 'free') {
        $this->nextAction = 'Готов к рейсу';
        return;
    }

    $actions = [
        'free'          => '1️⃣ Маршрут получен, движение к забою',
        'to_miner'      => '2️⃣ Прибыл к забою, ожидание загрузки',
        'loading'       => '3️⃣ Идет загрузка',
        'transporting'  => '4️⃣ Загружен, движение к месту разрузки',
        'unloading'     => '5️⃣ Идет разгрузка',
        'completed'     => '6️⃣ Разгрузился, свободен',
        'maintenance'   => '🔧 Обслуживание → Нажмите "В работу" после завершения',
        'fueling'       => '⛽ Заправка → Нажмите "В работу" после заправки', 
        'breakdown'     => '⚠️ Неисправность → Нажмите "В работу" после устранения неисправности',
    ];

    $this->nextAction = $actions[$status] ?? '❓ Неизвестный статус';
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
        case 'free': $this->truck->markAs('to_miner'); break;
        case 'to_miner': $this->truck->markAs('loading'); break;
        case 'loading': $this->truck->markAs('transporting'); break;
        case 'transporting': $this->truck->markAs('unloading'); break;
        case 'unloading': $this->truck->markAs('completed'); break;
        case 'completed': 
            $this->truck->markAs('free');
            if ($this->currentOrder) {
                $this->currentOrder->update(['active' => false]);
            }
            break;
    }
    
    // ✅ ПРАВИЛЬНЫЙ порядок:
    $this->loadTruck();                    // 1. Обновляем truck
    $this->setNextAction();                // 2. Пересчитываем nextAction

    // 🔥 ДЛЯ ВСЕХ: водитель + диспетчер
    $message = "🚛 {$this->truck->number} → {$this->nextAction}";
    Cache::put('realtime_notification', $message, 30);  // ← ОБЩИЙ канал!
    session()->flash('success', 'Статус обновлен!');
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
        // Логи только для отладки
        Log::info('DriverPanel render', [
            'truckId' => $this->truckId,
            'has_order' => $this->truck?->currentOrder ? 'ДА' : 'НЕТ'
        ]);
        
        return view('livewire.driver-panel');
    }



}
