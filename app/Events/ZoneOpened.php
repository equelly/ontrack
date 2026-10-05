<?php

namespace App\Events;

use App\Events\Contracts\TriggersRouteAssignment;
use App\Models\Zone;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * ZoneOpened — мастер открыл зону (delivery=true).
 *
 * Теперь зона доступна для разгрузки — Process Manager пытается
 * назначить маршруты ждущим драйверам (если раньше был NO_AVAILABLE_ZONES).
 */
class ZoneOpened implements TriggersRouteAssignment
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Zone $zone,
        public ?int $userId = null,
    ) {}

    public function getReason(): string
    {
        return 'zone_opened';
    }

    public function getMinerId(): ?int
    {
        return null; // Zone не привязана к конкретному забою
    }

    public function getMiningOrderId(): ?int
    {
        return null;
    }

    public function getZoneId(): ?int
    {
        return $this->zone->id;
    }

    public function getDumpId(): ?int
    {
        return $this->zone->dump_id;
    }
}
