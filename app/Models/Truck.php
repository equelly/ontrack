<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;

class Truck extends Model
{
    protected $fillable = [
        'number', 'brand', 'load_capacity',
        'driver_id', 'status', 'current_load', 'last_free_at', 'fuel_level', 'truck_model_id',
        'route_version', 'route_ack_version',
        'before_breakdown', 'pause_started_at',  // Для сохранения состояния при поломке/задержке
        'mileage', 'mileage_since_fuel',  // Пробег
        'moto_minutes', 'moto_minutes_since_to', 'last_to_type',  // Мото-часы
    ];

    protected $casts = [
        // Жестко приводим ID к числам, чтобы Livewire не превращал модель в массив
        'id' => 'integer',
        'driver_id' => 'integer',
        'truck_model_id' => 'integer',        
        'load_capacity' => 'decimal:2',
        'current_load' => 'decimal:2',
        'fuel_level' => 'decimal:1',  // литры топлива
        'last_free_at' => 'datetime',
        'pause_started_at' => 'datetime',
    ];

    protected $appends = ['fuelLiters', 'fuelPercent', 'fuelCapacity', 'fuelConsumption', 'fullName'];

    // Константы статусов
    const STATUS_FREE = 'free';
    const STATUS_TO_MINER = 'to_miner';
    const STATUS_LOADING = 'loading';
    const STATUS_TRANSPORTING = 'transporting';
    const STATUS_UNLOADING = 'unloading';
    const STATUS_BREAKDOWN = 'breakdown';
    const STATUS_MAINTENANCE = 'maintenance';
    const STATUS_FUELING = 'fueling';
    const STATUS_WAITING_LOADING = 'waiting_loading';
    const STATUS_WAITING_UNLOADING = 'waiting_unloading';
    const STATUS_DELAYED = 'delayed';

    public function truckModel()
    {
        return $this->belongsTo(TruckModel::class);
    }

    public function trips()
    {
        return $this->hasMany(TruckTrip::class, 'truck_id');
    }

    public function plannedTasks()
    {
        return $this->hasMany(TruckPlannedTask::class);
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function currentTrip()
    {
        return $this->hasOne(TruckTrip::class)
            ->whereNull('completed_at')
            ->latest();
    }

    /**
     * Текущая задача на обслуживании (в процессе)
     */
    public function currentServiceTask()
    {
        return $this->hasOne(TruckPlannedTask::class)
            ->whereNotNull('started_at')
            ->where('completed', false)
            ->latest();
    }

    // Геттеры для топлива
    public function getFuelCapacityAttribute()
    {
        return $this->truckModel?->fuel_capacity ?? 500;
    }

    /**
     * Расход топлива (л/мото-час)
     */
    public function getFuelConsumptionAttribute()
    {
        return $this->truckModel?->fuel_consumption ?? 25;
    }

    /**
     * Топливо в литрах (fuel_level теперь хранит литры)
     */
    public function getFuelLitersAttribute()
    {
        return round($this->fuel_level, 1);
    }

    /**
     * Топливо в процентах (вычисляемое)
     */
    public function getFuelPercentAttribute()
    {
        $capacity = $this->fuel_capacity;
        if ($capacity <= 0) {
            return 0;
        }
        return round(($this->fuel_level / $capacity) * 100, 1);
    }

    /**
     * Мото-часы до пустого бака
     */
    public function getMotoHoursUntilEmptyAttribute(): float
    {
        $consumption = $this->fuel_consumption;
        if ($consumption <= 0) {
            return 0;
        }
        return round($this->fuel_level / $consumption, 1);
    }

    public function getFullNameAttribute()
    {
        return ($this->truckModel?->full_name ?? 'Неизвестная модель') . ' #' . $this->number;
    }

    // Геттеры для пробега и мото-часов
    public function getMotoHoursAttribute(): float
    {
        return round($this->moto_minutes / 60, 1);
    }

    public function getMotoHoursSinceToAttribute(): float
    {
        return round($this->moto_minutes_since_to / 60, 1);
    }

    public function getMileageKmAttribute(): string
    {
        return number_format($this->mileage, 0, ',', ' ') . ' км';
    }

    /**
     * Получить мото-часы с момента последнего ТО
     */
    public function getMotoHoursSinceLastTO(): float
    {
        return $this->moto_hours_since_to;
    }

    public function miningOrders(): HasMany
    {
        return $this->hasMany(MiningOrder::class);
    }

    public function currentOrder()
    {
        return $this->hasOne(MiningOrder::class, 'truck_id')->where('active', true);
    }

    // Scope'ы
    public function scopeFree($query)
    {
        return $query->whereIn('status', ['free', 'completed']);
    }

    public function scopeAvailable($query)
    {
        return $query->whereIn('status', ['free', 'loading']);
    }

    public function scopeInProgress($query)
    {
        return $query->whereIn('status', ['loading', 'transporting', 'unloading']);
    }

    // Методы состояния
    public function isFree(): bool
    {
        return $this->status === 'free';
    }

    public function assignDriver(User $driver): self
    {
        $this->driver_id = $driver->id;
        $this->save();
        return $this;
    }

    public function markAs($status): self
    {
        $this->status = $status;

        if (in_array($status, ['free'])) {
            $this->current_load = 0;
            $this->last_free_at = now();
        }

        $this->save();
        return $this;
    }

    /**
     * Получить название статуса на русском
     */
    public function getStatusLabel(): string
    {
        return match($this->status) {
            'free' => 'В отстое',
            'completed' => 'Ожидает назначения',
            'to_miner' => 'К забою',
            'loading' => 'Погрузка',
            'transporting' => 'Перевозка',
            'unloading' => 'Разгрузка',
            'breakdown' => 'Поломка',
            'maintenance' => 'Обслуживание',
            'fueling' => 'Заправка',
            'waiting_loading' => 'Ожидание погрузки',
            'waiting_unloading' => 'Ожидание назначения для разгрузки',
            'delayed' => 'Задержка',
            default => $this->status,
        };
    }

    /**
     * Получить CSS класс для статуса
     */
    public function getStatusClass(): string
    {
        return match($this->status) {
            'free' => 'secondary',
            'completed' => 'success',
            'to_miner' => 'info',
            'loading' => 'warning',
            'transporting' => 'primary',
            'unloading' => 'secondary',
            'breakdown' => 'danger',
            'maintenance' => 'warning',
            'fueling' => 'info',
            'waiting_loading' => 'warning',
            'waiting_unloading' => 'danger',
            'delayed' => 'warning',
            default => 'secondary',
        };
    }

    /**
     * Все статусы с названиями
     */
    public static function getAllStatuses(): array
    {
        return [
            'free' => 'В отстое',
            'completed' => 'Ожидает назначения',
            'to_miner' => 'К забою',
            'loading' => 'Погрузка',
            'transporting' => 'Перевозка',
            'unloading' => 'Разгрузка',
            'breakdown' => 'Поломка',
            'maintenance' => 'Обслуживание',
            'fueling' => 'Заправка',
            'waiting_loading' => 'Ожидание погрузки',
            'waiting_unloading' => 'Ожидание назначения для разгрузки',
            'delayed' => 'Задержка',
        ];
    }
}
