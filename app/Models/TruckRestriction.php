<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TruckRestriction extends Model
{
    use HasFactory;

    protected $fillable = ['truck_id', 'rock_id', 'reason'];

    public function truck()
    {
        return $this->belongsTo(Truck::class);
    }

    public function rock()
    {
        return $this->belongsTo(Rock::class);
    }
}
