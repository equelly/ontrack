<?php

namespace App\Events\Contracts;

/**
 * Interface TriggersRouteAssignment — общий интерфейс для ВСЕХ событий,
 * которые могут сделать доступными новые маршруты для ожидающих драйверов.
 *
 * Реализуется классами:
 *   - LoadingCompleted       (завершение погрузки → забой освободился)
 *   - MiningOrderActivated   (диспетчер активировал MiningOrder)
 *   - ZoneOpened             (мастер открыл зону — delivery=true)
 *   - ZoneClosed              (мастер закрыл зону — delivery=false)
 *   - RockChanged            (сменили породу в забое)
 *   - BermCompleted          (завершена обваловка зоны)
 *
 * RouteAssignmentListener (Process Manager) может принимать события через union тип
 * или через полиморфизм (если использовать Event Subscriber pattern).
 *
 * Интерфейс обязывает реализовать геттеры для данных, которые нужны Process Manager'у.
 */
interface TriggersRouteAssignment
{
    /**
     * Человекочитаемая причина события (для логирования).
     * Например: 'loading_completed', 'order_activated', 'zone_opened'.
     */
    public function getReason(): string;

    /**
     * ID забоя, если релевантно (может быть null если событие не связано с конкретным забоем).
     */
    public function getMinerId(): ?int;

    /**
     * ID MiningOrder, если релевантно.
     */
    public function getMiningOrderId(): ?int;

    /**
     * ID зоны, если релевантно.
     */
    public function getZoneId(): ?int;

    /**
     * ID отвала, если релевантно.
     */
    public function getDumpId(): ?int;
}