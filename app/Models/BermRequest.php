<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BermRequest — запрос на обваловку зоны.
 *
 * Обваловка — это отсыпка предохранительного вала для безопасности
 * ведения горных работ.
 *
 * Жизненный цикл:
 *   pending → in_progress → completed
 *                       ↘ cancelled (мастер отменил)
 *
 * Пока запрос активен (pending или in_progress):
 *  - обычные маршруты на эту зону не назначаются
 *  - свободные самосвалы направляются на обваловку в первую очередь
 *
 * Завершается когда:
 *  - trucks_completed достиг trucks_needed (автоматически)
 *  - мастер вручную отменяет запрос (cancelled)
 */
class BermRequest extends Model
{
    use HasFactory;

    const STATUS_PENDING     = 'pending';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED   = 'completed';
    const STATUS_CANCELLED   = 'cancelled';

    const STATUSES_ACTIVE = [self::STATUS_PENDING, self::STATUS_IN_PROGRESS];

    protected $fillable = [
        'zone_id',
        'dump_id',
        'trucks_needed',
        'trucks_assigned',
        'trucks_completed',
        'rock_id',
        'status',
        'created_by',
        'completed_by',
        'completed_at',
    ];

    protected $casts = [
        'trucks_needed'     => 'integer',
        'trucks_assigned'   => 'integer',
        'trucks_completed'  => 'integer',
        'completed_at'      => 'datetime',
    ];

    // ==========================================
    // СВЯЗИ
    // ==========================================

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function dump(): BelongsTo
    {
        return $this->belongsTo(Dump::class);
    }

    public function rock(): BelongsTo
    {
        return $this->belongsTo(Rock::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    // ==========================================
    // ПОМОЩНИКИ
    // ==========================================

    public function isActive(): bool
    {
        return in_array($this->status, self::STATUSES_ACTIVE);
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Сколько самосвалов ещё нужно назначить.
     */
    public function remainingToAssign(): int
    {
        return max(0, $this->trucks_needed - $this->trucks_assigned);
    }

    /**
     * Сколько рейсов ещё должно завершиться до окончания обваловки.
     */
    public function remainingToComplete(): int
    {
        return max(0, $this->trucks_needed - $this->trucks_completed);
    }

    /**
     * Прогресс в процентах (по завершённым рейсам).
     */
    public function progressPercent(): float
    {
        if ($this->trucks_needed === 0) {
            return 100.0;
        }
        return round(($this->trucks_completed / $this->trucks_needed) * 100, 1);
    }

    /**
     * Скоуп: активные запросы.
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', self::STATUSES_ACTIVE);
    }

    /**
     * Скоуп: активные запросы для конкретной зоны.
     */
    public function scopeActiveForZone($query, int $zoneId)
    {
        return $query->where('zone_id', $zoneId)->whereIn('status', self::STATUSES_ACTIVE);
    }
}
