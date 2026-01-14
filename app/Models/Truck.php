<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Truck extends Model
{
    protected $fillable = [
        'number', 'brand', 'load_capacity', 
        'driver_id', 'status', 'current_load', 'last_free_at'
    ];

    protected $casts = [
        'load_capacity' => 'decimal:2',
        'current_load' => 'decimal:2',
        'last_free_at' => 'datetime',
    ];

    // Связи
    public function miningOrders(): HasMany
    {
        return $this->hasMany(MiningOrder::class);
    }

    public function currentOrder()
    {
        return $this->hasOne(MiningOrder::class)
            ->where('active', true)
            ->latest();
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    // Scope'ы
    public function scopeFree($query)
    {
        return $query->where('status', 'free');
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

