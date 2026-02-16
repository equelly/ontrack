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
        'route_version', 'route_ack_version'
    ];

    protected $casts = [
        'load_capacity' => 'decimal:2',
        'current_load' => 'decimal:2',
        'last_free_at' => 'datetime',
    ];
    protected $appends = ['fuelLiters', 'fuelCapacity', 'fuelConsumption', 'fullName'];
    
    

    public function truckModel()
    {
        return $this->belongsTo(TruckModel::class);
    }

    public function plannedTasks()
    {
        return $this->hasMany(TruckPlannedTask::class);
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }


    // Геттеры для топлива
    public function getFuelCapacityAttribute()
    {
        return $this->truckModel?->fuel_capacity ?? 500;
    }

    public function getFuelConsumptionAttribute()
    {
        return $this->truckModel?->fuel_consumption ?? 35;
    }

    public function getFuelLitersAttribute()
    {
        return round(($this->fuel_level / 100) * $this->fuel_capacity, 1);
    }

    public function getFullNameAttribute()
    {
        return ($this->truckModel?->full_name ?? 'Неизвестная модель') . ' #' . $this->number;
    }

    
    public function miningOrders(): HasMany
    {
        return $this->hasMany(MiningOrder::class);
    }


    // app/Models/Truck.php
    public function currentOrder()
    {
        return $this->hasOne(MiningOrder::class, 'truck_id')->where('active', true);
    }

    // Scope'ы
    public function scopeFree($query)
    {
        
        //  'free' И 'completed' - оба свободны для назначения
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
}

