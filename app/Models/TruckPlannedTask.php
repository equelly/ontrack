<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TruckPlannedTask extends Model
{
    // Константы типов задач
    const TYPE_FUELING = 'fueling';
    const TYPE_MAINTENANCE = 'maintenance';
    const TYPE_TIRE_INFLATION = 'tire_inflation';
    const TYPE_WHEEL_TIGHTENING = 'wheel_tightening';
    const TYPE_INSPECTION = 'inspection';

    // Константы типов ТО
    const TO_1 = 'TO-1';
    const TO_2 = 'TO-2';

    // Длительности по умолчанию (в минутах)
    const DURATION_FUELING = 15;
    const DURATION_TO_1 = 120; // 2 часа
    const DURATION_TO_2 = 240; // 4 часа
    const DURATION_TIRE_INFLATION = 10;
    const DURATION_WHEEL_TIGHTENING = 15;

    protected $fillable = [
        'truck_id',
        'task_type',
        'to_type',
        'scheduled_at',
        'completed',
        'completed_at',
        'queue_position',
        'duration_minutes',
        'started_at',
        'service_post_id',
        'notes',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'completed_at' => 'datetime',
        'started_at' => 'datetime',
        'completed' => 'boolean',
    ];

    // ==========================================
    // ОТНОШЕНИЯ
    // ==========================================

    public function truck(): BelongsTo
    {
        return $this->belongsTo(Truck::class);
    }

    public function servicePost(): BelongsTo
    {
        return $this->belongsTo(ServicePost::class);
    }

    // ==========================================
    // СКОПЫ
    // ==========================================

    public function scopePending($query)
    {
        return $query->where('completed', false);
    }

    public function scopeCompleted($query)
    {
        return $query->where('completed', true);
    }

    public function scopeInQueue($query)
    {
        return $query->whereNull('started_at')->where('completed', false);
    }

    public function scopeInProgress($query)
    {
        return $query->whereNotNull('started_at')->where('completed', false);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('task_type', $type);
    }

    // ==========================================
    // МЕТОДЫ
    // ==========================================

    /**
     * Получить название типа задачи на русском
     */
    public function getTypeLabel(): string
    {
        return match($this->task_type) {
            self::TYPE_FUELING => 'Заправка',
            self::TYPE_MAINTENANCE => 'ТО (' . ($this->to_type ?? 'ТО') . ')',
            self::TYPE_TIRE_INFLATION => 'Подкачка шин',
            self::TYPE_WHEEL_TIGHTENING => 'Обтяжка колёс',
            self::TYPE_INSPECTION => 'Инспекция',
            default => $this->task_type,
        };
    }

    /**
     * Получить длительность задачи
     */
    public function getDuration(): int
    {
        if ($this->duration_minutes) {
            return $this->duration_minutes;
        }

        return match($this->task_type) {
            self::TYPE_FUELING => self::DURATION_FUELING,
            self::TYPE_MAINTENANCE => $this->to_type === self::TO_2 ? self::DURATION_TO_2 : self::DURATION_TO_1,
            self::TYPE_TIRE_INFLATION => self::DURATION_TIRE_INFLATION,
            self::TYPE_WHEEL_TIGHTENING => self::DURATION_WHEEL_TIGHTENING,
            default => 30,
        };
    }

    /**
     * Начать выполнение задачи
     */
    public function start(int $servicePostId): void
    {
        $this->started_at = now();
        $this->service_post_id = $servicePostId;
        $this->save();
    }

    /**
     * Завершить задачу
     */
    public function complete(): void
    {
        $this->completed = true;
        $this->completed_at = now();
        $this->save();

        // Освобождаем пост
        if ($this->service_post_id) {
            $post = ServicePost::find($this->service_post_id);
            if ($post) {
                $post->release();
            }
        }

        // Обновляем данные грузовика
        $this->updateTruckAfterService();
    }

    /**
     * Обновить данные грузовика после обслуживания
     */
    protected function updateTruckAfterService(): void
    {
        $truck = $this->truck;

        switch ($this->task_type) {
            case self::TYPE_FUELING:
                $truck->mileage_since_fuel = 0;
                break;

            case self::TYPE_MAINTENANCE:
                $truck->moto_minutes_since_to = 0;
                $truck->last_to_type = $this->to_type;
                break;
        }

        $truck->save();
    }

    /**
     * Получить все типы задач
     */
    public static function getAllTypes(): array
    {
        return [
            self::TYPE_FUELING => 'Заправка',
            self::TYPE_MAINTENANCE => 'Техническое обслуживание',
            self::TYPE_TIRE_INFLATION => 'Подкачка шин',
            self::TYPE_WHEEL_TIGHTENING => 'Обтяжка колёс',
            self::TYPE_INSPECTION => 'Инспекция',
        ];
    }
}
