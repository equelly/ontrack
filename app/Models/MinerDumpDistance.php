<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class MinerDumpDistance extends Model
{
    use HasFactory;

    protected $table = 'miner_dump_distances'; // имя таблицы

    // 🆕 Если у тебя нет стандартных timestamps — отключаем
    public $timestamps = false;

    protected $fillable = [
        'miner_id',
        'dump_id', 
        'distance_km',
        'travel_time_hours'
    ];

        protected $casts = [
        'distance_km' => 'decimal:2',  // 2 знака после запятой
    ];
    // Связи с моделями
    public function miner(): BelongsTo
    {
        return $this->belongsTo(Miner::class);
    }

    public function dump(): BelongsTo
    {
        return $this->belongsTo(Dump::class);
    }

    // 🆕 Автоматическое обновление майнера при изменении расстояния
    protected static function boot()
    {
        parent::boot();

        // При создании/обновлении расстояния
        static::saved(function ($distance) {
            if (Auth::check() && $distance->miner) {
                // Обновляем связанного майнера
                $distance->miner->update([
                    'last_updated_by' => Auth::id(),
                    'last_updated_at' => now(),
                ]);
            }
        });

        // При удалении расстояния
        static::deleted(function ($distance) {
            if (Auth::check() && $distance->miner) {
                // Проверяем, есть ли ещё расстояния у майнера
                $hasOtherDistances = MinerDumpDistance::where('miner_id', $distance->miner_id)->exists();

                if (!$hasOtherDistances) {
                    // Если это последнее расстояние — обновляем майнера
                    $distance->miner->update([
                        'last_updated_by' => Auth::id(),
                        'last_updated_at' => now(),
                    ]);
                }
            }
        });
    }
}

