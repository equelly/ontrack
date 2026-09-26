<?php

namespace App\Events;

use App\Models\Zone;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Событие: зона заполняется (80-99%), предупреждение заранее.
 *
 * В отличие от ZoneNeedsBerm (которое отправляется при 100%),
 * это событие отправляется когда зона заполняется и мастеру/диспетчеру
 * нужно заранее планировать обваловку или переключение зоны.
 *
 * Событие летит на 3 канала:
 *  - 'master'     — мастер видит уведомление в Панели Мастера
 *  - 'dispatcher' — диспетчер видит уведомление в Панели Диспетчера
 *  - 'zones'      — публичный канал для всех, кто слушает изменения зон
 */
class ZoneFillWarning implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Zone $zone;
    public float $volume;
    public float $capacity;
    public float $fillPercent;
    public ?float $hoursToOverflow;

    public function __construct(Zone $zone, ?float $hoursToOverflow = null)
    {
        $this->zone = $zone->fresh();
        $this->volume = (float) $this->zone->volume;
        $this->capacity = (float) $this->zone->capacity;
        $this->fillPercent = $this->capacity > 0
            ? round(($this->volume / $this->capacity) * 100, 1)
            : 100.0;
        $this->hoursToOverflow = $hoursToOverflow;
    }

    /**
     * Каналы для оповещения.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('master'),
            new Channel('dispatcher'),
            new Channel('zones'),
        ];
    }

    public function broadcastWith(): array
    {
        $hours = $this->hoursToOverflow !== null
            ? round($this->hoursToOverflow, 1)
            : null;

        // Текст сообщения зависит от степени заполнения
        $severity = $this->fillPercent >= 95 ? 'critical' : 'warning';

        if ($this->fillPercent >= 95) {
            $message = "⚠️ Зона «{$this->zone->name_zone}» ({$this->zone->dump?->name_dump}) заполнена на {$this->fillPercent}% — критическая отметка! Срочно нужна обваловка.";
        } else {
            $message = "📊 Зона «{$this->zone->name_zone}» ({$this->zone->dump?->name_dump}) заполнена на {$this->fillPercent}%.";
            if ($hours !== null && $hours < 3) {
                $message .= " При текущей скорости — переполнение через {$hours} ч. Готовьте обваловку.";
            } elseif ($hours !== null) {
                $message .= " Прогноз переполнения — через {$hours} ч.";
            } else {
                $message .= " Требуется контроль.";
            }
        }

        return [
            'zone_id'           => $this->zone->id,
            'zone_name'         => $this->zone->name_zone,
            'dump_id'           => $this->zone->dump_id,
            'dump_name'         => $this->zone->dump?->name_dump,
            'volume'            => $this->volume,
            'capacity'          => $this->capacity,
            'fill_percent'      => $this->fillPercent,
            'hours_to_overflow' => $hours,
            'severity'          => $severity,
            'message'           => $message,
            'action'            => 'zone_fill_warning',
        ];
    }

    public function broadcastAs(): string
    {
        return 'zone.fill.warning';
    }
}
