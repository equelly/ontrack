<?php

namespace App\Domain;


class TruckStatus
{
    public static function label(string $status): string
    {
        return match ($status) {
            'to_miner'      => 'В пути к забою',
            'loading'       => 'Идёт загрузка',
            'transporting'  => 'Движение к месту выгрузки',
            'unloading'     => 'Разгрузка',
            'completed'     => 'Рейс завершён',
            'free'          => 'Готов к работе',
            'breakdown'     => 'Поломка',
            default         => $status,
        };
    }

    public static function nextTransition(string $status): ?array
    {
        return match ($status) {
            'to_miner'     => ['to' => 'loading',      'label' => 'Начать загрузку'],
            'loading'      => ['to' => 'transporting', 'label' => 'Завершить загрузку'],
            'transporting' => ['to' => 'unloading',    'label' => 'Прибыл на выгрузку'],
            'unloading'    => ['to' => 'completed',    'label' => 'Завершить рейс'],
           
            default        => null,
        };
    }

    public static function canTransition(string $from, string $to): bool
    {
        $next = self::nextTransition($from);
        return $next && $next['to'] === $to;
    }
}
