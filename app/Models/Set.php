<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Set extends Model
{
    use HasFactory;

      //модель при создании связана с таблицей 'sets' установим защиту дополнительно в модели
      protected $table = 'sets';
      //снимем защиту для возможности записи атрубутов модели в БД
      protected $guarded = []; // ... или false

//метод связывает объект класса Set к User через промежуточную табл. "set_users" затем указывается id-поле этого класса 'set_id'  потом поле к которму привязывается 'user_id'

    public function users(){
        return $this->belongsToMany(User::class, 'set_users', 'set_id', 'user_id') ;
    }

    public function mashines(){
        return $this->belongsToMany(Mashine::class, 'mashine_sets', 'set_id', 'mashine_id') ;
    }


}
