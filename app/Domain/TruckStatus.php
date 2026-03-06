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
            'completed'          => 'Рейс завершён',
            'waiting_loading'    => 'Ожидание погрузки',
            'waiting_unloading'  => 'Ожидание разгрузки',
            'delayed'            => 'Задержка в пути',
            'breakdown'          => 'Поломка',
            'maintenance'        => 'Обслуживание',
            'fueling'            => 'Заправка',
            default              => $status,
        };
    }

    public static function nextTransition(string $status): ?array
    {
        return match ($status) {
            'free'              => ['to' => 'to_miner',    'label' => 'Получить маршрут'],
            'to_miner'          => ['to' => 'loading',     'label' => 'Начать загрузку'],
            'loading'           => ['to' => 'transporting','label' => 'Завершить загрузку'],
            'transporting'      => ['to' => 'unloading',   'label' => 'Прибыл на выгрузку'],
            'unloading'         => ['to' => 'completed',   'label' => 'Завершить рейс'],
            'waiting_loading'   => ['to' => 'loading',     'label' => 'Начать загрузку'],
            'waiting_unloading' => ['to' => 'unloading',   'label' => 'Начать разгрузку'],
            'delayed'           => ['to' => 'transporting','label' => 'Продолжить путь'],
            'breakdown'         => ['to' => 'free',        'label' => 'Поломка устранена'],
            'maintenance'       => ['to' => 'free',        'label' => 'Готов к работе'],
            'fueling'           => ['to' => 'free',        'label' => 'Готов к работе'],
            default             => null,
        };
    }

    public static function canTransition(string $from, string $to): bool
    {
        $allowed = [
            'free'              => ['to_miner', 'maintenance', 'fueling'],
            'to_miner'          => ['loading', 'delayed', 'breakdown'],
            'loading'           => ['transporting', 'waiting_loading', 'breakdown'],
            'transporting'      => ['unloading', 'delayed', 'breakdown'],
            'unloading'         => ['completed', 'waiting_unloading', 'breakdown'],
            'completed'         => [],
            'waiting_loading'   => ['loading', 'breakdown'],
            'waiting_unloading' => ['unloading', 'breakdown'],
            'delayed'           => ['transporting', 'breakdown'],
            'breakdown'         => ['free'],
            'maintenance'       => ['free'],
            'fueling'           => ['free'],
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
}