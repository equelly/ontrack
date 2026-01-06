<?php
// app/Models/MiningOrder.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Miner;      // ← Добавил!
use App\Models\Dump;       // ← Добавил!
use App\Models\User;       // ← Добавил!

class MiningOrder extends Model
{
    protected $fillable = [
        'miner_id', 'dump_id', 'operator_id', 
        'distance_km', 'score', 'active', 'assigned_round'
    ];

    protected $casts = [
        'active' => 'boolean',
        'distance_km' => 'decimal:2',
        'score' => 'decimal:2',
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
}

