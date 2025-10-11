<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Route extends Model
{
    use HasFactory;

    protected $table = 'routes';
    //снимем защиту для возможности записи атрубутов модели в БД
    protected $guarded = []; // ... или false
    
        public function dump()
    {
        return $this->belongsTo(Dump::class);
    }
        public function miner()
    {
        return $this->belongsTo(Miner::class);
    }


}
