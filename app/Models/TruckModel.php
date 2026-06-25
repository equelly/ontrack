<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TruckModel extends Model
{
    protected $fillable = [
        'brand', 'model', 'full_name', 
        'fuel_capacity', 'fuel_consumption', 'load_capacity'
    ];

    public function trucks()
    {
        return $this->hasMany(Truck::class);
    }
}
