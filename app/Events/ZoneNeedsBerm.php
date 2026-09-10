<?php

namespace App\Events;

use App\Models\Zone;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Событие: зона заполнена до предела, требуется обваловка.
 *
 * Обваловка — это предохранительный вал для безопасности ведения
 * горных работ. Когда зона заполнена до capacity
 * Событие летит на 3 канала:
 *  - 'master'   — мастер видит уведомление в Панели Мастера
 *  - 'dispatcher' — диспетчер видит уведомление в Панели Диспетчера
 *  - 'zones'    — публичный канал для всех, кто слушает изменения зон
 */
class ZoneNeedsBerm implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Zone $zone;
    public float $volume;
    public float $capacity;
    public float $fillPercent;

    public function __construct(Zone $zone)
    {
        $this->zone = $zone->fresh();
        $this->volume = (float) $this->zone->volume;
        $this->capacity = (float) $this->zone->capacity;
        $this->fillPercent = $this->capacity > 0
            ? round(($this->volume / $this->capacity) * 100, 1)
            : 100.0;
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
        return [
            'zone_id'       => $this->zone->id,
            'zone_name'     => $this->zone->name_zone,
            'dump_id'       => $this->zone->dump_id,
            'dump_name'     => $this->zone->dump?->name_dump,
            'volume'        => $this->volume,
            'capacity'      => $this->capacity,
            'fill_percent'  => $this->fillPercent,
            'message'       => "Зона «{$this->zone->name_zone}» ({$this->zone->dump?->name_dump}) заполнена на {$this->fillPercent}%. Требуется обваловка!",
            'action'        => 'zone_needs_berm',
        ];
    }

    public function broadcastAs(): string
    {
        return 'zone.needs.berm';
    }
}
