<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TruckPlannedTask extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'truck_id', 'task_type', 'fuel_filled', 'fuel_before', 
        'fuel_after', 'completed', 'completed_at'
    ];
    
    protected $casts = [
        'completed' => 'boolean',
        'fuel_filled' => 'decimal:2',
        'fuel_before' => 'decimal:2',
        'fuel_after' => 'decimal:2',
    ];
    
    public function truck()
    {
        return $this->belongsTo(Truck::class);
    }
}
