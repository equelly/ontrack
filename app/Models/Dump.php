<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Dump extends Model
{
    use HasFactory;


    //модель при создании связана с таблицей 'dumps' установим защиту дополнительно в модели
    protected $table = 'dumps';
    //снимем защиту для возможности записи атрубутов модели в БД
        protected $fillable = [
        'name_dump',
        'last_updated_by',
        'last_updated_at',
        'loader_zone_id',  
    ];

        // ✅ АВТОМАТИЧЕСКОЕ ОБНОВЛЕНИЕ ПОЛЕЙ АУДИТА
    protected static function boot()
    {
        parent::boot();

        // При СОЗДАНИИ новой записи
        static::creating(function ($dump) {
            $dump->last_updated_at = now();  // Текущее время
            $dump->last_updated_by = Auth::id()?? 1;  // ID пользователя или 1 (admin)
        });

        // При КАЖДОМ ОБНОВЛЕНИИ
        static::updating(function ($dump) {
            $dump->last_updated_at = now();
            $dump->last_updated_by = Auth::id()?? 1;
        });
    }

        public function routes()
    {
        return $this->hasMany(Route::class);
    }

        public function zones()
    {
        return $this->hasMany(Zone::class);
    }

    public function lastEditor()
    {
        return $this->belongsTo(User::class, 'last_updated_by');
    }
        // ОТНОШЕНИЕ: dump → выбранная зона отгрузки
    public function loaderZone()
    {
        return $this->belongsTo(Zone::class, 'loader_zone_id');
    }

    protected $casts = [
    'last_updated_at' => 'datetime',
    ];



}
