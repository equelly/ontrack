<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * MashineSet — промежуточная таблица связи many-to-many между
 * оборудованием (Mashine) и расходниками (Set).
 *
 * Семантика:
 *   Запись существует = расходник нужно завезти на это оборудование.
 *   Запись удаляется = расходник завезён (или больше не нужен).
 *
 * Использование:
 *   - MasterPanel / ExcavatorPanel: мастер/машинист отмечает чекбоксами
 *     нужные расходники → создаёт MashineSet
 *   - MaintenancePanel: механик видит список расходников для завозки →
 *     отмечает галочкой «завезено» → удаляет MashineSet
 */
class MashineSet extends Model
{
    use HasFactory;

    protected $table = 'mashine_sets';

    protected $fillable = [
        'mashine_id',
        'set_id',
    ];

    public $timestamps = true;

    // ==========================================
    // СВЯЗИ
    // ==========================================

    /**
     * Оборудование, на которое нужно завезти расходник.
     */
    public function mashine(): BelongsTo
    {
        return $this->belongsTo(Mashine::class);
    }

    /**
     * Расходник (запчасть/ГСМ/комплектующая), который нужно завезти.
     */
    public function set(): BelongsTo
    {
        return $this->belongsTo(Set::class);
    }

    // ==========================================
    // СКОПЫ
    // ==========================================

    /**
     * Только записи с существующим оборудованием (на случай если mashine удалён).
     */
    public function scopeWithMashine($query)
    {
        return $query->whereHas('mashine');
    }

    /**
     * Только записи с существующим расходником.
     */
    public function scopeWithSet($query)
    {
        return $query->whereHas('set');
    }
}