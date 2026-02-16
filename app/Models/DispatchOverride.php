<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DispatchOverride extends Model
{
    protected $fillable = [
        'truck_id',
        'mining_order_id',
        'type',
        'active',
        'used_at',
        'created_by'
    ];

    // Грузовик
    public function truck()
    {
        return $this->belongsTo(Truck::class);
    }

    // Маршрут
    public function miningOrder()
    {
        return $this->belongsTo(MiningOrder::class);
    }

    // Диспетчер
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

