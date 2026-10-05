<?php

namespace App\Events;

use App\Events\Contracts\TriggersRouteAssignment;
use App\Models\Zone;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * BermCompleted — завершена обваловка зоны.
 *
 * Зона снова доступна для разгрузки (если volume < capacity).
 * Process Manager пытается назначить маршруты ждущим драйверам.
 */
class BermCompleted implements TriggersRouteAssignment
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Zone $zone,
        public ?int $userId = null,
    ) {}

    public function getReason(): string
    {
        return 'berm_completed';
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