<?php

namespace App\Services;

class ScoreCalculatorService
{
    /**
     * Рассчитать score по режиму
     */
    public function calculate(array $data, string $mode): float
    {
        return match($mode) {
            'volume' => $this->volumeScore($data['volume'], $data['distance']),
            'distance' => $this->distanceScore($data['distance']),
            default => $this->balanceScore($data['volume'], $data['distance'], $data['dump_capacity'] ?? 60),
        };
    }

    /**
     * Баланс объёма и расстояния (30/70)
     */
    public function balanceScore(float $volume, float $distance, float $dumpCapacity = 60): float
    {
        $volumePercent = ($volume / $dumpCapacity) * 100;
        $volumeScore = max(0, 100 - $volumePercent);
        $distancePenalty = $distance * 10;
        $distanceScore = max(0, 100 - $distancePenalty);
        
        return round(($volumeScore * 0.3) + ($distanceScore * 0.7), 2);
    }

    /**
     * Приоритет по объёму (меньшие объемы первыми)
     */
    public function volumeScore(float $volume, float $distance): float
    {
        $inverseVolume = (1 / ($volume + 1)) * 1000;
        $distancePenalty = $distance * 3;
        
        return round($inverseVolume - $distancePenalty, 2);
    }

    /**
     * Приоритет по расстоянию (короче = лучше)
     */
    public function distanceScore(float $distance): float
    {
        return round((1 / ($distance + 0.1)) * 100, 2);
    }
}
