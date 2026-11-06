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

trait WithEvents
{
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

}
