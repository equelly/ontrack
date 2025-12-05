<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Set;
use App\Models\Order;

class Mashine extends Model
{
    use HasFactory;

     //модель при создании связана с таблицей 'mashines' установим защиту дополнительно в модели
     protected $table = 'mashines';
     //снимем защиту для возможности записи атрубутов модели в БД
     protected $guarded = []; // ... или false

     //one to many
      /**
     * Get the orders for the mashine.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function sets(){
        return $this->belongsToMany(Set::class, 'mashine_sets', 'mashine_id', 'set_id') ;
    }
}
