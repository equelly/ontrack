<?php

namespace App\Events;

use App\Events\Contracts\TriggersRouteAssignment;
use App\Models\Truck;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * LoadingCompleted — событие: погрузка самосвала завершена.
 *
 * ДВОЙНОЕ НАЗНАЧЕНИЕ (event-driven архитектура):
 *   1. Broadcasting на канал truck.{truckId} — водитель получает toast
 *      "Погрузка завершена, начните движение к месту разгрузки"
 *   2. Domain event — RouteAssignmentListener (Process Manager) ловит его
 *      через интерфейс TriggersRouteAssignment, находит всех ждущих
 *      драйверов (trucks.is_searching_route=true) и пытается назначить им
 *      маршрут (т.к. забой освободился).
 * ВАЖНО: implements ShouldBroadcastNow (не ShouldBroadcast) — отправляет
 * в Reverb СИНХРОННО, без queue. ShouldBroadcast через queue НЕ работает
 * (BroadcastEvent jobs не обрабатываются). ShouldBroadcastNow обходит queue.
 */
class LoadingCompleted implements ShouldBroadcastNow, TriggersRouteAssignment
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Truck $truck;
    public ?string $newZone;
    public ?string $newDump;

    // Доменные данные для Process Manager (не идут в broadcast)
    public ?int $minerId;
    public ?int $miningOrderId;
    public ?int $zoneId;
    public ?int $dumpId;

    public function __construct(
        Truck $truck,
        ?string $newZone = null,
        ?string $newDump = null,
        // Новые опциональные параметры — для Process Manager
        ?int $minerId = null,
        ?int $miningOrderId = null,
        ?int $zoneId = null,
        ?int $dumpId = null,
    ) {
        $this->truck = $truck;
        $this->newZone = $newZone;
        $this->newDump = $newDump;
        $this->minerId = $minerId;
        $this->miningOrderId = $miningOrderId;
        $this->zoneId = $zoneId;
        $this->dumpId = $dumpId;
    }

    // === Реализация TriggersRouteAssignment ===

    public function getReason(): string
    {
        return 'loading_completed';
    }

    public function getMinerId(): ?int
    {
        return $this->minerId;
    }

    public function getMiningOrderId(): ?int
    {
        return $this->miningOrderId;
    }

    public function getZoneId(): ?int
    {
        return $this->zoneId;
    }

    public function getDumpId(): ?int
    {
        return $this->dumpId;
    }

    // === Broadcasting ===

    public function broadcastOn()
    {
        return new PrivateChannel('truck.' . $this->truck->id);
    }

    public function broadcastWith(): array
    {
        $data = [
            'truck_id' => $this->truck->id,
            'truck_number' => $this->truck->number,
            'message' => "Погрузка завершена, начните движение к месту разгрузки",
        ];

        if ($this->newZone) {
            $data['zone_changed'] = true;
            $data['new_zone'] = $this->newZone;
            $data['new_dump'] = $this->newDump;
            $data['message'] = "Погрузка завершена. Место разгрузки изменено: {$this->newDump} - {$this->newZone}";
        }

        return $data;
    }

    public function broadcastAs(): string
    {
        return 'loading.completed';
    }
}