<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AiAlert — алерт, обнаруженный системой аномалий.
 *
 * Алерты персистентны: хранятся в БД, пока диспетчер не подтвердит.
 */
class AiAlert extends Model
{
    use HasFactory;

    // Типы
    const TYPE_TRUCK_ANOMALY = 'truck_anomaly';
    const TYPE_ZONE_OVERFLOW = 'zone_overflow';
    const TYPE_PRODUCTIVITY_DROP = 'productivity_drop';
    const TYPE_EMPTY_RUN_HIGH = 'empty_run_high';
    const TYPE_MAINTENANCE_PREDICT = 'maintenance_predict';

    // Важность
    const SEVERITY_INFO = 'info';
    const SEVERITY_WARNING = 'warning';
    const SEVERITY_CRITICAL = 'critical';

    // Статусы
    const STATUS_NEW = 'new';
    const STATUS_ACKNOWLEDGED = 'acknowledged';
    const STATUS_RESOLVED = 'resolved';

    protected $fillable = [
        'type',
        'severity',
        'title',
        'message',
        'data',
        'entity_type',
        'entity_id',
        'status',
        'acknowledged_at',
        'acknowledged_by',
    ];

    protected $casts = [
        'data' => 'array',
        'acknowledged_at' => 'datetime',
    ];

    // ==========================================
    // СВЯЗИ
    // ==========================================

    public function acknowledger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    // ==========================================
    // СКОПЫ
    // ==========================================

    public function scopeNew($query)
    {
        return $query->where('status', self::STATUS_NEW);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', [self::STATUS_NEW, self::STATUS_ACKNOWLEDGED]);
    }

    public function scopeCritical($query)
    {
        return $query->where('severity', self::SEVERITY_CRITICAL);
    }

    // ==========================================
    // ПОМОЩНИКИ
    // ==========================================

    public function isNew(): bool
    {
        return $this->status === self::STATUS_NEW;
    }

    public function isCritical(): bool
    {
        return $this->severity === self::SEVERITY_CRITICAL;
    }

    /**
     * Подтвердить алерт (диспетчер увидел).
     */
    public function acknowledge(int $userId): void
    {
        $this->update([
            'status' => self::STATUS_ACKNOWLEDGED,
            'acknowledged_at' => now(),
            'acknowledged_by' => $userId,
        ]);
    }

    /**
     * Отметить как решённый.
     */
    public function resolve(): void
    {
        $this->update(['status' => self::STATUS_RESOLVED]);
    }

    /**
     * Проверить, есть ли уже активный алерт этого типа для этой сущности.
     * Чтобы не создавать дубликаты.
     */
    public static function hasActiveAlert(string $type, ?int $entityId = null, ?string $entityType = null): bool
    {
        $query = self::active()->where('type', $type);

        if ($entityId && $entityType) {
            $query->where('entity_type', $entityType)
                  ->where('entity_id', $entityId);
        }

        return $query->exists();
    }
}