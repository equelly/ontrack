<?php

namespace App\Events;

use App\Models\BermRequest;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Событие: прогресс обваловки зоны.
 *
 * Летит на 3 канала:
 *  - 'master'     — мастер видит прогресс в Панели Мастера
 *  - 'dispatcher' — диспетчер информируется
 *  - 'zones'      — публичный канал для всех
 *
 * Payload содержит:
 *  - request_id, zone_id, zone_name, dump_name
 *  - trucks_needed, trucks_assigned, trucks_completed
 *  - progress_percent
 *  - status: pending / in_progress / completed / cancelled
 *  - message: готовое сообщение для UI
 */
class BermProgress implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $payload;

    public function __construct(BermRequest $request)
    {
        $request->refresh();
        $zone = $request->zone;
        $dump = $request->dump;

        $progressPercent = $request->progressPercent();

        $message = match($request->status) {
            BermRequest::STATUS_PENDING     => "Зона «{$zone?->name_zone}»: ожидание самосвалов для обваловки (нужно {$request->trucks_needed})",
            BermRequest::STATUS_IN_PROGRESS => "Зона «{$zone?->name_zone}»: обваловка в процессе — {$request->trucks_completed}/{$request->trucks_needed} самосвалов ({$progressPercent}%)",
            BermRequest::STATUS_COMPLETED   => "✅ Зона «{$zone?->name_zone}»: обваловка завершена ({$request->trucks_completed} самосвалов)",
            BermRequest::STATUS_CANCELLED   => "❌ Зона «{$zone?->name_zone}»: обваловка отменена мастером",
            default                          => "Зона «{$zone?->name_zone}»: обваловка — {$request->status}",
        };

        $this->payload = [
            'request_id'         => $request->id,
            'zone_id'            => $request->zone_id,
            'zone_name'          => $zone?->name_zone,
            'dump_id'            => $request->dump_id,
            'dump_name'          => $dump?->name_dump,
            'rock_id'            => $request->rock_id,
            'trucks_needed'      => $request->trucks_needed,
            'trucks_assigned'    => $request->trucks_assigned,
            'trucks_completed'   => $request->trucks_completed,
            'progress_percent'   => $progressPercent,
            'status'             => $request->status,
            'message'            => $message,
        ];
    }

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
        return $this->payload;
    }

    public function broadcastAs(): string
    {
        return 'berm.progress';
    }
}