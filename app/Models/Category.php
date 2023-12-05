<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

 //модель при создании связана с таблицей 'categories' установим защиту дополнительно в модели
 protected $table = 'categories';
 //снимем защиту для возможности записи атрубутов модели в БД
 protected $guarded = []; // ... или false

     /**
     * Get the category for the orders.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
