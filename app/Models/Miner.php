<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class Miner extends Model
{
    use HasFactory;

    //модель при создании связана с таблицей 'miners' установим защиту дополнительно в модели
    protected $table = 'miners';
    //снимем защиту для возможности записи атрубутов модели в БД
    protected $guarded = []; // ... или false

        protected $fillable = [
        'name_miner', 
        'active',
        'last_updated_at',  //  Добавляем в fillable audit
        'last_updated_by',  // 
    ];
        protected $casts = [
        'active' => 'boolean',
        'last_updated_at' => 'datetime',  //  Кастим как дату
    ];
        public function rocks()
    {
        return $this->belongsToMany(Rock::class, 'miner_rock');
    }
        /**
     * Текущая добываемая порода (выбирает экскаваторщик)
     */
    public function currentRock()
    {
        return $this->belongsTo(Rock::class, 'current_rock_id');
    }
    
        public function dumps()
    {
        return $this->belongsToMany(Dump::class, 'miner_dump_distances')
                    ->withPivot(['distance_km']) // поля из промежуточной таблицы
                    ->withTimestamps();
    }

        public function distances(): HasMany
    {
        return $this->hasMany(MinerDumpDistance::class, 'miner_id');
    }

        // Связь с пользователем, кто обновил
    public function lastUpdater()
    {
        return $this->belongsTo(User::class, 'last_updated_by');
    }

        // 🆕 Аксессор для удобного отображения
    protected function lastUpdated(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->last_updated_at? [
                'time' => $this->last_updated_at->format('d.m.Y H:i'),
                'user' => $this->lastUpdater?->name?? 'Система',
                'ago' => $this->last_updated_at->diffForHumans(),
            ]: null
        );
    }

        // 🆕 Автоматическое обновление при сохранении
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($miner) {
            // Если авторизован — записываем кто и когда
            if (Auth::check()) {
                $miner->last_updated_by = Auth::id();
                $miner->last_updated_at = now();
            }
        });
    }

}
