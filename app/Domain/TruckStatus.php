<?php

namespace App\Domain;

class TruckStatus
{
    public static function label(string $status): string
    {
        return match ($status) {
            'free'               => 'Готов к работе',
            'to_miner'           => 'В пути к забою',
            'loading'            => 'Идёт загрузка',
            'transporting'       => 'Движение к месту выгрузки',
            'unloading'          => 'Разгрузка',
            'completed'          => 'Ожидает назначения',
            'waiting_loading'    => 'Ожидание погрузки',
            'waiting_unloading'  => 'Ожидание назначения зоны разгрузки',
            'delayed'            => 'Задержка в пути',
            'breakdown'          => 'Поломка',
            'maintenance'        => 'Обслуживание',
            'fueling'            => 'Заправка',
            'service'            => 'Сервис',
            default              => $status,
        };
    }

    public static function color(string $status): string
    {
        return match ($status) {
            'free'               => 'secondary', // В отстое
            'to_miner'           => 'info',
            'loading'            => 'warning',
            'transporting'       => 'primary',
            'unloading'          => 'secondary',
            'completed'          => 'success', // Готов к назначению
            'waiting_loading'    => 'warning',
            'waiting_unloading'  => 'danger', // Требует внимания диспетчера
            'delayed'            => 'warning',
            'breakdown'          => 'danger',
            'maintenance'        => 'secondary',
            'fueling'            => 'info',
            'service'            => 'warning',
            default              => 'secondary',
        };
    }

    public static function nextTransition(string $status): ?array
    {
        return match ($status) {
            'free'              => ['to' => 'to_miner',    'label' => 'Получить маршрут'],
            'completed'         => ['to' => 'to_miner',    'label' => 'Получить маршрут'],
            'to_miner'          => ['to' => 'loading',     'label' => 'Начать загрузку'],
            'loading'           => ['to' => 'transporting','label' => 'Завершить загрузку'],
            'transporting'      => ['to' => 'unloading',   'label' => 'Прибыл на выгрузку'],
            'unloading'         => ['to' => 'completed',   'label' => 'Завершить рейс'],
            'waiting_loading'   => ['to' => 'loading',     'label' => 'Начать загрузку'],
            'waiting_unloading' => ['to' => 'transporting','label' => 'Зона назначена, в пути'],
            'delayed'           => ['to' => 'transporting','label' => 'Продолжить путь'],
            'breakdown'         => ['to' => 'free',        'label' => 'Поломка устранена'],
            'maintenance'       => ['to' => 'free',        'label' => 'Готов к работе'],
            'fueling'           => ['to' => 'free',        'label' => 'Готов к работе'],
            'service'           => ['to' => 'free',        'label' => 'Готов к работе'],
            default             => null,
        };
    }

    public static function canTransition(string $from, string $to): bool
    {
        $allowed = [
            'free'              => ['to_miner', 'maintenance', 'fueling', 'service'],
            'completed'         => ['to_miner', 'free'], // Получить маршрут или в отстой
            'to_miner'          => ['loading', 'delayed', 'breakdown'],
            'loading'           => ['transporting', 'waiting_loading', 'breakdown'],
            'transporting'      => ['unloading', 'delayed', 'breakdown'],
            'unloading'         => ['completed', 'waiting_unloading', 'breakdown'],
            'waiting_loading'   => ['loading', 'breakdown'],
            'waiting_unloading' => ['transporting', 'breakdown'],
            'delayed'           => ['transporting', 'breakdown'],
            'breakdown'         => ['free'],
            'maintenance'       => ['free'],
            'fueling'           => ['free'],
            'service'           => ['free'],
        ];

        return in_array($to, $allowed[$from] ?? []);
    }

    public static function workingStatuses(): array
    {
        return ['to_miner', 'loading', 'transporting', 'unloading'];
    }

    public static function waitingStatuses(): array
    {
        return ['waiting_loading', 'waiting_unloading', 'delayed'];
    }

    /**
     * Все возможные переходы для статуса (для UI с несколькими кнопками)
     */
    public static function getAllowedTransitions(string $status): array
    {
        $transitions = [
            'free' => [
                ['to' => 'to_miner', 'label' => 'Получить маршрут'],
            ],
            'completed' => [
                ['to' => 'to_miner', 'label' => 'Получить маршрут'],
                ['to' => 'free', 'label' => 'В отстой'],
            ],
            'to_miner' => [
                ['to' => 'loading', 'label' => 'Начать загрузку'],
            ],
            'loading' => [
                ['to' => 'transporting', 'label' => 'Завершить загрузку'],
            ],
            'transporting' => [
                ['to' => 'unloading', 'label' => 'Прибыл на выгрузку'],
            ],
            'unloading' => [
                ['to' => 'completed', 'label' => 'Завершить рейс'],
            ],
            'waiting_loading' => [
                ['to' => 'loading', 'label' => 'Начать загрузку'],
            ],
            'waiting_unloading' => [
                ['to' => 'transporting', 'label' => 'Зона назначена, в пути'],
            ],
            'delayed' => [
                ['to' => 'transporting', 'label' => 'Продолжить путь'],
            ],
            'breakdown' => [
                ['to' => 'free', 'label' => 'Поломка устранена'],
            ],
            'maintenance' => [
                ['to' => 'free', 'label' => 'Готов к работе'],
            ],
            'fueling' => [
                ['to' => 'free', 'label' => 'Готов к работе'],
            ],
            'service' => [
                ['to' => 'free', 'label' => 'Готов к работе'],
            ],
        ];

        return $transitions[$status] ?? [];
    }
}
