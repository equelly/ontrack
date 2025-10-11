<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Miner extends Model
{
    use HasFactory;

    //модель при создании связана с таблицей 'miners' установим защиту дополнительно в модели
    protected $table = 'miners';
    //снимем защиту для возможности записи атрубутов модели в БД
    protected $guarded = []; // ... или false

    public function routes()
{
    return $this->hasMany(Route::class);
}

}
