<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServicePost extends Model
{
    const TYPE_FUELING = 'fueling';
    const TYPE_MAINTENANCE = 'maintenance';
    const TYPE_TIRE_SERVICE = 'tire_service';

    protected $fillable = [
        'type',
        'name',
        'is_active',
        'is_occupied',
        'current_truck_id',
        'occupied_at',
        'estimated_free_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_occupied' => 'boolean',
        'occupied_at' => 'datetime',
    ];

    // ==========================================
    // ОТНОШЕНИЯ
    // ==========================================

    public function currentTruck(): BelongsTo
    {
        return $this->belongsTo(Truck::class, 'current_truck_id');
    }

    public function plannedTasks()
    {
        return $this->hasMany(TruckPlannedTask::class, 'service_post_id');
    }

    // ==========================================
    // СКОПЫ
    // ==========================================

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeOccupied($query)
    {
        return $query->where('is_occupied', true);
    }

    public function scopeFree($query)
    {
        return $query->where('is_occupied', false);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    // ==========================================
    // МЕТОДЫ
    // ==========================================

    /**
     * Занять пост
     */
    public function occupy(Truck $truck, int $durationMinutes): void
    {
        $this->is_occupied = true;
        $this->current_truck_id = $truck->id;
        $this->occupied_at = now();
        $this->estimated_free_at = $durationMinutes;
        $this->save();
    }

    /**
     * Освободить пост
     */
    public function release(): void
    {
        $this->is_occupied = false;
        $this->current_truck_id = null;
        $this->occupied_at = null;
        $this->estimated_free_at = null;
        $this->save();
    }

    /**
     * Получить название типа поста
     */
    public function getTypeLabel(): string
    {
        return match($this->type) {
            self::TYPE_FUELING => 'Заправка',
            self::TYPE_MAINTENANCE => 'ТО',
            self::TYPE_TIRE_SERVICE => 'Шиномонтаж',
            default => $this->type,
        };
    }

    /**
     * Получить время до освобождения (в минутах)
     */
    public function getMinutesUntilFree(): ?int
    {
        if (!$this->is_occupied || !$this->occupied_at || !$this->estimated_free_at) {
            return null;
        }

        $elapsed = now()->diffInMinutes($this->occupied_at);
        $remaining = $this->estimated_free_at - $elapsed;

        return max(0, $remaining);
    }

    /**
     * Получить все типы постов
     */
    public static function getAllTypes(): array
    {
        return [
            self::TYPE_FUELING => 'Заправка',
            self::TYPE_MAINTENANCE => 'Техническое обслуживание',
            self::TYPE_TIRE_SERVICE => 'Шиномонтаж',
        ];
    }

    /**
     * Создать посты по умолчанию
     */
    public static function createDefaultPosts(): void
    {
        // 2 поста заправки
        for ($i = 1; $i <= 2; $i++) {
            self::firstOrCreate([
                'type' => self::TYPE_FUELING,
                'name' => "Заправка {$i}",
            ], [
                'is_active' => true,
            ]);
        }

        // 2 поста ТО
        for ($i = 1; $i <= 2; $i++) {
            self::firstOrCreate([
                'type' => self::TYPE_MAINTENANCE,
                'name' => "ТО {$i}",
            ], [
                'is_active' => true,
            ]);
        }

        // 3 поста шиномонтажа
        for ($i = 1; $i <= 3; $i++) {
            self::firstOrCreate([
                'type' => self::TYPE_TIRE_SERVICE,
                'name' => "Шиномонтаж {$i}",
            ], [
                'is_active' => true,
            ]);
        }
    }

    /**
     * Получить свободные активные посты
     */
    public static function getFreeActivePosts(string $type): \Illuminate\Database\Eloquent\Collection
    {
        return self::ofType($type)
            ->active()
            ->free()
            ->get();
    }

    /**
     * Получить количество активных постов типа
     */
    public static function getActivePostsCount(string $type): int
    {
        return self::ofType($type)->active()->count();
    }
}
