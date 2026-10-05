<?php

namespace App\Events;

use App\Events\Contracts\TriggersRouteAssignment;
use App\Models\MiningOrder;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * MiningOrderActivated — диспетчер активировал MiningOrder.
 *
 * Теперь маршрут доступен для назначения драйверам в ожидании.
 * Process Manager (RouteAssignmentListener) ловит это событие и
 * пытается назначить маршрут ждущим драйверам.
 *
 * НЕ broadcasting — это внутреннее доменное событие.
 */
class MiningOrderActivated implements TriggersRouteAssignment
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public MiningOrder $order,
        public ?int $userId = null,
    ) {}

    public function getReason(): string
    {
        return 'order_activated';
    }

    public function getMinerId(): ?int
    {
        return $this->order->miner_id;
    }

    public function getMiningOrderId(): ?int
    {
        return $this->order->id;
    }

    public function getZoneId(): ?int
    {
        return $this->order->zone_id;
    }

    public function getDumpId(): ?int
    {
        return $this->order->dump_id;
    }
}
