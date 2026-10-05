<?php

namespace App\Events;

use App\Events\Contracts\TriggersRouteAssignment;
use App\Models\Miner;
use App\Models\Rock;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * RockChanged — экскаваторщик сменил текущую породу в забое.
 *
 * Теперь MiningOrder для этого забоя может быть доступен для других
 * самосвалов (без ограничений по породе). Process Manager пытается
 * назначить маршруты ждущим драйверам.
 */
class RockChanged implements TriggersRouteAssignment
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Miner $miner,
        public ?Rock $oldRock = null,
        public ?Rock $newRock = null,
        public ?int $userId = null,
    ) {}

    public function getReason(): string
    {
        return 'rock_changed';
    }

    public function getMinerId(): ?int
    {
        return $this->miner->id;
    }

    public function getMiningOrderId(): ?int
    {
        return null;
    }

    public function getZoneId(): ?int
    {
        return null;
    }

    public function getDumpId(): ?int
    {
        return null;
    }
}