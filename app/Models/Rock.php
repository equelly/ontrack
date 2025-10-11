<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rock extends Model
{
    use HasFactory;

    //модель при создании связана с таблицей 'rocks' установим защиту дополнительно в модели
    protected $table = 'rocks';
    //снимем защиту для возможности записи атрубутов модели в БД
    protected $guarded = []; // ... или false

    public function zones() {
        return $this->belongsToMany(Zone::class, 'rock_zone');
    }
}
