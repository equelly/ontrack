<?php

namespace App\Services;

use Carbon\Carbon;

class ShiftService
{
    /**
     * Calculate the current shift based on a fixed reference date.
     *
     * @return array
     */
    public function getCurrentShift(): array
    {
        $tz = new \DateTimeZone('Europe/Moscow');
        
        // Точка отсчета: 11 июля 2026, 07:30 (МСК) — старт дневной смены Смены 1
        $reference = new \DateTime('2026-07-11 07:30:00', $tz);
        $now = new \DateTime('now', $tz);
        
        // 1. Определяем тип смены по текущему времени
        $hour = (int) $now->format('H');
        $minute = (int) $now->format('i');
        $isDay = false;
        
        if (($hour > 7 || ($hour == 7 && $minute >= 30)) && ($hour < 19 || ($hour == 19 && $minute < 30))) {
            // Время от 07:30 до 19:30 — День
            $isDay = true;
            $blockStart = new \DateTime($now->format('Y-m-d') . ' 07:30:00', $tz);
        } elseif ($hour >= 19 || ($hour == 19 && $minute >= 30)) {
            // Время от 19:30 сегодня — Ночь
            $isDay = false;
            $blockStart = new \DateTime($now->format('Y-m-d') . ' 19:30:00', $tz);
        } else {
            // Время после полуночи до 07:30 — Ночь, началась вчера
            $isDay = false;
            $blockStart = new \DateTime($now->format('Y-m-d') . ' 19:30:00', $tz);
            $blockStart->modify('-1 day');
        }
        
        // 2. Вычисляем индекс 12-часового блока
        // Разница в секундах между началом текущего блока и точкой отсчета
        $secondsDiff = $blockStart->getTimestamp() - $reference->getTimestamp();
        $hoursDiff = $secondsDiff / 3600;
        
        // Цикл 4 дня = 96 часов = 8 блоков по 12 часов
        // Получаем индекс от 0 до 7
        $index = (int) (($hoursDiff / 12) % 8);
        
        // Если дата в прошлом (отрицательная разница),修正 индекс
        if ($index < 0) {
            $index += 8;
        }
        
        // 3. Карта смен: 1Д, 4Н, 2Д, 1Н, 3Д, 2Н, 4Д, 3Н
        $shiftsMap = [1, 4, 2, 1, 3, 2, 4, 3];
        $shiftId = $shiftsMap[$index];
        
        return [
            'shift_id' => $shiftId,
            'shift_type' => $isDay ? 'day' : 'night',
            'start_time' => \Illuminate\Support\Carbon::instance($blockStart),
            'end_time' => \Illuminate\Support\Carbon::instance($blockStart)->addHours(12),
        ];
    }
}