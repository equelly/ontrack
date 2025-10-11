<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Zone extends Model
{
    use HasFactory;

    public function dump() {
        return $this->belongsTo(Dump::class);
    }
    public function routes() {
        return $this->hasMany(Route::class);
    }
    public function rocks() {
        return $this->belongsToMany(Rock::class, 'rock_zone');
    }
}
