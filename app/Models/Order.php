<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class Order extends Model
{
    use HasFactory;

    //для удаления с возможностью восстановления применяем SoftDeletes в модели и при создании миграции
    //восстнавливается методом <Model>::withTrached()->find(<id>)->restore();
    use SoftDeletes;

    //модель при создании связана с таблицей 'orders' установим защиту дополнительно в модели
    protected $table = 'orders';
    //снимем защиту для возможности записи атрубутов модели в БД
    protected $guarded = []; // ... или false
    //объект класса Order связан с объектом Category через поле (свойство)'category_id' табл. 'orders' (класса Order) и поле ('id') табл.'categories'
    public function category(){
        return $this->belongsTo(Category::class, 'category_id', 'id');

    }
    //объект класса Order связан с объектом Mashine через поле (свойство)'mashine_id' табл. 'orders' (класса Order) и поле ('id')  табл. 'mashines'
    public function mashine(){
        return $this->belongsTo(Mashine::class, 'mashine_id', 'id');

    }
    //объект класса Order связан с объектом Category через поле (свойство)'category_id' табл. 'orders' (класса Order) и поле ('id')  табл. 'users'
    public function user(){
        return $this->belongsTo(User::class, 'user_id_req', 'id');

    }
}
