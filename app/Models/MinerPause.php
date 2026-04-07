<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * MinerPause - история простоев забоя
 */
class MinerPause extends Model
{
    // Типы простоев (соответствуют статусам Miner)
    const TYPE_BREAKDOWN = 'breakdown';
    const TYPE_MAINTENANCE = 'maintenance';
    const TYPE_DISMANTLING = 'dismantling';
    const TYPE_ACCESS_SETUP = 'access_setup';

    // Разрешаем все поля для mass assignment
    protected $guarded = [];

    protected $fillable = [
        'miner_id',
        'type',
        'reason',
        'started_at',
        'ended_at',
        'duration_seconds',
        'started_by',
        'ended_by',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    // ==========================================
    // СВЯЗИ
    // ==========================================

    public function miner()
    {
        return $this->belongsTo(Miner::class);
    }

    public function starter()
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function ender()
    {
        return $this->belongsTo(User::class, 'ended_by');
    }

    // ==========================================
    // МЕТОДЫ
    // ==========================================

    /**
     * Завершить паузу
     */
    public function end(?int $endedBy = null): void
    {
        $this->update([
            'ended_at' => now(),
            'duration_seconds' => $this->started_at->diffInSeconds(now()),
            'ended_by' => $endedBy,
        ]);
    }

    /**
     * Текущая длительность паузы (для активных пауз)
     */
    public function getCurrentDuration(): int
    {
        if ($this->ended_at) {
            return $this->duration_seconds;
        }

        return $this->started_at->diffInSeconds(now());
    }

    /**
     * Форматированная длительность
     */
    public function getFormattedDuration(): string
    {
        $seconds = $this->getCurrentDuration();
        return $this->formatSeconds($seconds);
    }

    /**
     * Активна ли пауза
     */
    public function isActive(): bool
    {
        return $this->ended_at === null;
    }

    /**
     * Название типа паузы
     */
    public function getTypeLabel(): string
    {
        return self::typeLabel($this->type);
    }

    /**
     * Статический метод для получения названия типа
     */
    public static function typeLabel(string $type): string
    {
        return match($type) {
            self::TYPE_BREAKDOWN => 'Поломка',
            self::TYPE_MAINTENANCE => 'Обслуживание',
            self::TYPE_DISMANTLING => 'Разбор забоя',
            self::TYPE_ACCESS_SETUP => 'Устройство подъезда',
            default => $type,
        };
    }

    /**
     * Все типы простоев
     */
    public static function types(): array
    {
        return [
            self::TYPE_BREAKDOWN => 'Поломка',
            self::TYPE_MAINTENANCE => 'Обслуживание',
            self::TYPE_DISMANTLING => 'Разбор забоя',
            self::TYPE_ACCESS_SETUP => 'Устройство подъезда',
        ];
    }

    /**
     * Форматирование секунд в ЧЧ:ММ:СС
     */
    private function formatSeconds(int $seconds): string
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $secs);
        }

        return sprintf('%02d:%02d', $minutes, $secs);
    }
}
