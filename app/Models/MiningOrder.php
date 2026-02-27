<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Miner;      
use App\Models\Dump;       
use App\Models\User;       

class MiningOrder extends Model
{
    protected $fillable = [
        'miner_id', 
        'dump_id', 
        'operator_id', 
        'truck_id',        
        'distance_km', 
        'score', 
        'active', 
        'assigned_round',  
        'priority',
        'completed_at',
        'wrr_cursor',           // ← добавлено для wrr распределения рейсов
        'last_assigned_at',     // ← добавлено 
    ];

    protected $casts = [
        'active' => 'boolean',
        'distance_km' => 'decimal:2',
        'score' => 'decimal:2',
        'priority' => 'integer',
        'completed_at' => 'datetime',
        'wrr_cursor' => 'decimal:2',      // ← добавлено
        'last_assigned_at' => 'datetime', // ← добавлено
    ];

    public function miner() 
    { 
        return $this->belongsTo(Miner::class); 
    }
    
    public function dump() 
    { 
        return $this->belongsTo(Dump::class); 
    }
    
    public function operator() 
    { 
        return $this->belongsTo(User::class); 
    }

    public function isActive(): bool 
    { 
        return $this->active; 
    }
    
    public function scopeActive($query) 
    { 
        return $query->where('active', true); 
    }
    
    public function scopeCompleted($query) 
    { 
        return $query->where('active', false); 
    }

    public function truck() { 
        return $this->belongsTo(Truck::class); 
    }

    public function scopeForTruck($query, Truck $truck) {
        return $query->where('truck_id', $truck->id)->latest(); 
    }
}