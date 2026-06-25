<?php

namespace App\Domain;

class MinerStatus
{
    // Константы статусов
    public const ACTIVE = 'active';
    public const BREAKDOWN = 'breakdown';
    public const MAINTENANCE = 'maintenance';
    public const FACE_DISMANTLING = 'face_dismantling';
    public const ACCESS_SETUP = 'access_setup';
    public const RELOCATION = 'relocation';

    /**
     * Все возможные статусы
     */
    public static function all(): array
    {
        return [
            self::ACTIVE,
            self::BREAKDOWN,
            self::MAINTENANCE,
            self::FACE_DISMANTLING,
            self::ACCESS_SETUP,
            self::RELOCATION,
        ];
    }

    /**
     * Метка статуса на русском
     */
    public static function label(string $status): string
    {
        return match ($status) {
            self::ACTIVE          => 'В работе',
            self::BREAKDOWN       => 'Поломка',
            self::MAINTENANCE     => 'Обслуживание',
            self::FACE_DISMANTLING => 'Разбор забоя',
            self::ACCESS_SETUP    => 'Устройство подъезда',
            self::RELOCATION      => 'Переезд',
            default               => $status,
        };
    }

    /**
     * Цвет для Bootstrap badge
     */
    public static function color(string $status): string
    {
        return match ($status) {
            self::ACTIVE          => 'success',
            self::BREAKDOWN       => 'danger',
            self::MAINTENANCE     => 'warning',
            self::FACE_DISMANTLING => 'info',
            self::ACCESS_SETUP    => 'secondary',
            self::RELOCATION      => 'primary',
            default               => 'secondary',
        };
    }

    /**
     * CSS класс для строки таблицы
     */
    public static function rowClass(string $status): string
    {
        return match ($status) {
            self::ACTIVE          => '',
            self::BREAKDOWN       => 'table-danger',
            self::MAINTENANCE     => 'table-warning',
            self::FACE_DISMANTLING => 'table-info',
            self::ACCESS_SETUP    => 'table-secondary',
            self::RELOCATION      => 'table-primary',
            default               => '',
        };
    }

    /**
     * Иконка Font Awesome
     */
    public static function icon(string $status): string
    {
        return match ($status) {
            self::ACTIVE          => 'fa-check-circle',
            self::BREAKDOWN       => 'fa-wrench',
            self::MAINTENANCE     => 'fa-tools',
            self::FACE_DISMANTLING => 'fa-hammer',
            self::ACCESS_SETUP    => 'fa-road',
            self::RELOCATION      => 'fa-truck-moving',
            default               => 'fa-question',
        };
    }

    /**
     * Является ли статус активным (забой работает)
     */
    public static function isActive(string $status): bool
    {
        return $status === self::ACTIVE;
    }

    /**
     * Является ли статус проблемным (требует внимания)
     */
    public static function isProblematic(string $status): bool
    {
        return in_array($status, [self::BREAKDOWN, self::MAINTENANCE]);
    }

    /**
     * Статусы, при которых забой недоступен для назначения
     */
    public static function unavailableStatuses(): array
    {
        return [self::BREAKDOWN, self::MAINTENANCE, self::FACE_DISMANTLING, self::ACCESS_SETUP, self::RELOCATION];
    }

    /**
     * Возможные переходы из статуса
     */
    public static function getAllowedTransitions(string $status): array
    {
        $transitions = [
            self::ACTIVE => [
                ['to' => self::BREAKDOWN, 'label' => 'Поломка'],
                ['to' => self::MAINTENANCE, 'label' => 'На обслуживание'],
                ['to' => self::FACE_DISMANTLING, 'label' => 'Разбор забоя'],
                ['to' => self::RELOCATION, 'label' => 'Переезд'],
            ],
            self::BREAKDOWN => [
                ['to' => self::ACTIVE, 'label' => 'В работу'],
                ['to' => self::MAINTENANCE, 'label' => 'На обслуживание'],
            ],
            self::MAINTENANCE => [
                ['to' => self::ACTIVE, 'label' => 'В работу'],
                ['to' => self::BREAKDOWN, 'label' => 'Обнаружена поломка'],
            ],
            self::FACE_DISMANTLING => [
                ['to' => self::ACCESS_SETUP, 'label' => 'Устройство подъезда'],
                ['to' => self::RELOCATION, 'label' => 'Переезд'],
            ],
            self::ACCESS_SETUP => [
                ['to' => self::ACTIVE, 'label' => 'В работу'],
                ['to' => self::RELOCATION, 'label' => 'Переезд'],
            ],
            self::RELOCATION => [
                ['to' => self::ACTIVE, 'label' => 'В работу'],
                ['to' => self::ACCESS_SETUP, 'label' => 'Устройство подъезда'],
            ],
        ];

        return $transitions[$status] ?? [];
    }
}