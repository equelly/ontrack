<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rock extends Model
{
    use HasFactory;

    protected $table = 'rocks';
    protected $fillable = ['name'];

    public function zones() {
        return $this->belongsToMany(Zone::class, 'rock_zone');
    }
}