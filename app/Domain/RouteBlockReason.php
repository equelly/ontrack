<?php

namespace App\Domain;

/**
 * Причины, по которым маршрут не может быть назначен грузовику.
 *
 * Используется в:
 * - RouteAssignmentService::diagnoseForTruck() — для возврата точной причины отказа
 * - DriverPanel::assignRoute() — для показа водителю
 * - MainDispatcherPanel::getTruckDiagnosticsProperty() — для вкладки «Самосвалы»
 */
class RouteBlockReason
{
    // Коды причин
    const TRUCK_BUSY = 'truck_busy';
    const MINER_NOT_FOUND = 'miner_not_found';
    const MINER_INACTIVE = 'miner_inactive';
    const MINER_NOT_WORKING = 'miner_not_working';
    const NO_CURRENT_ROCK = 'no_current_rock';
    const MINER_OVERLOADED = 'miner_overloaded';
    const ROCK_RESTRICTED = 'rock_restricted';
    const NO_AVAILABLE_ZONES = 'no_available_zones';
    const NO_ACTIVE_ORDERS = 'no_active_orders';

    /**
     * Человекочитаемая метка причины.
     */
    public static function label(string $code): string
    {
        return match($code) {
            self::TRUCK_BUSY           => 'Грузовик занят',
            self::MINER_NOT_FOUND      => 'Забой не найден',
            self::MINER_INACTIVE       => 'Забой не в работе',
            self::MINER_NOT_WORKING    => 'Вспомогательные работы',
            self::NO_CURRENT_ROCK      => 'Не задана текущая порода в забое',
            self::MINER_OVERLOADED     => 'Забой перегружен самосвалами',
            self::ROCK_RESTRICTED      => 'Порода запрещена для этого самосвала',
            self::NO_AVAILABLE_ZONES   => 'Нет доступных зон разгрузки для породы',
            self::NO_ACTIVE_ORDERS     => 'Нет активных маршрутов',
            default                    => 'Неизвестная причина',
        };
    }

    /**
     * Короткая метка для интерфейса (для бейджа в Панели Диспетчера).
     */
    public static function shortLabel(string $code): string
    {
        return match($code) {
            self::TRUCK_BUSY           => 'Занят',
            self::MINER_NOT_FOUND      => 'Забой не найден',
            self::MINER_INACTIVE       => 'Забой не в работе',
            self::MINER_NOT_WORKING    => 'Погрузка приостановлена',
            self::NO_CURRENT_ROCK      => 'Нет породы',
            self::MINER_OVERLOADED     => 'Забой перегружен',
            self::ROCK_RESTRICTED      => 'Порода запрещена',
            self::NO_AVAILABLE_ZONES   => 'Нет зон',
            self::NO_ACTIVE_ORDERS     => 'Нет маршрутов',
            default                    => '—',
        };
    }

    /**
     * Цвет бейджа (Tailwind) для отображения в UI.
     */
    public static function color(string $code): string
    {
        return match($code) {
            self::TRUCK_BUSY           => 'red',       // критично
            self::MINER_NOT_FOUND      => 'red',
            self::MINER_INACTIVE       => 'amber',    // предупреждение
            self::MINER_NOT_WORKING    => 'amber',
            self::NO_CURRENT_ROCK      => 'amber',
            self::MINER_OVERLOADED     => 'amber',
            self::ROCK_RESTRICTED      => 'amber',
            self::NO_AVAILABLE_ZONES   => 'amber',
            self::NO_ACTIVE_ORDERS     => 'slate',    // инфо
            default                    => 'slate',
        };
    }

    /**
     * Иконка FontAwesome для UI.
     */
    public static function icon(string $code): string
    {
        return match($code) {
            self::TRUCK_BUSY           => 'fa-ban',
            self::MINER_NOT_FOUND      => 'fa-exclamation-triangle',
            self::MINER_INACTIVE       => 'fa-power-off',
            self::MINER_NOT_WORKING    => 'fa-pause-circle',
            self::NO_CURRENT_ROCK      => 'fa-question-circle',
            self::MINER_OVERLOADED     => 'fa-users',
            self::ROCK_RESTRICTED      => 'fa-ban',
            self::NO_AVAILABLE_ZONES   => 'fa-map-marked-alt',
            self::NO_ACTIVE_ORDERS     => 'fa-list',
            default                    => 'fa-question',
        };
    }

    /**
     * Действие, которое должен предпринять диспетчер/мастер для устранения причины.
     */
    public static function action(string $code): string
    {
        return match($code) {
            self::TRUCK_BUSY           => 'Дождитесь завершения текущего рейса',
            self::MINER_NOT_FOUND      => 'Проверьте корректность miner_id в маршруте',
            self::MINER_INACTIVE       => 'Дождитесь включения забой в работу',
            self::MINER_NOT_WORKING    => 'Ожидание начала погрузочных работ',
            self::NO_CURRENT_ROCK      => 'Задайте текущую породу в забое машинистом)',
            self::MINER_OVERLOADED     => 'Дождитесь погрузки самосвалов в забое',
            self::ROCK_RESTRICTED      => 'Снимите ограничение на эту породу для самосвала',
            self::NO_AVAILABLE_ZONES   => 'Откройте зону разгрузки или привяжите породу к зоне',
            self::NO_ACTIVE_ORDERS     => 'Создайте/активируйте маршрут в Панели Диспетчера',
            default                    => 'Обратитесь к администратору',
        };
    }

    /**
     * Все коды причин (для итерации).
     */
    public static function all(): array
    {
        return [
            self::TRUCK_BUSY,
            self::MINER_NOT_FOUND,
            self::MINER_INACTIVE,
            self::MINER_NOT_WORKING,
            self::NO_CURRENT_ROCK,
            self::MINER_OVERLOADED,
            self::ROCK_RESTRICTED,
            self::NO_AVAILABLE_ZONES,
            self::NO_ACTIVE_ORDERS,
        ];
    }
}
