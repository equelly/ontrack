<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Бросается, когда RouteAssignmentService не может назначить маршрут грузовику.
 *
 * Особенность: carries массив диагностики — по какому маршруту какая причина отказа.
 * Используется в DriverPanel и MainDispatcherPanel для показа точной причины.
 */
class NoRouteAvailableException extends RuntimeException
{
    /**
     * @param string $message Короткое сообщение для логов
     * @param array $diagnostics Подробная диагностика:
     *     [
     *         'primary_reason' => string,         // код основной причины (RouteBlockReason::*)
     *         'summary' => [                       // сводка по причинам
     *             'reason_code' => count,
     *             ...
     *         ],
     *         'orders' => [                        // детали по каждому маршруту
     *             ['order_id' => 1, 'miner_id' => 2, 'reason' => 'miner_inactive', 'miner_name' => 'Забой 1'],
     *             ...
     *         ],
     *     ]
     */
    public function __construct(
        string $message = 'Нет доступных маршрутов',
        private array $diagnostics = []
    ) {
        parent::__construct($message);
    }

    public function getDiagnostics(): array
    {
        return $this->diagnostics;
    }

    public function getPrimaryReason(): ?string
    {
        return $this->diagnostics['primary_reason'] ?? null;
    }

    public function getSummary(): array
    {
        return $this->diagnostics['summary'] ?? [];
    }

    public function getOrders(): array
    {
        return $this->diagnostics['orders'] ?? [];
    }
}
