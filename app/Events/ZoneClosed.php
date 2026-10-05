<?php

namespace App\Events;

use App\Events\Contracts\TriggersRouteAssignment;
use App\Models\Zone;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * ZoneClosed — мастер закрыл зону (delivery=false).
 *
 * Зона больше не принимает разгрузку. Process Manager пытается
 * переназначить ждущих драйверов на другие доступные зоны
 * (если они были направлены на эту зону).
 */
class ZoneClosed implements TriggersRouteAssignment
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Zone $zone,
        public ?int $userId = null,
    ) {}

    public function getReason(): string
    {
        return 'zone_closed';
    }

    public function getMinerId(): ?int
    {
        return null;
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