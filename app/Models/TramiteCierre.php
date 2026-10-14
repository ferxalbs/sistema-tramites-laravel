<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tramite_id
 * @property int $entrega_id
 * @property int|null $cerrado_por
 * @property string $resumen
 * @property string|null $observacion
 * @property Carbon $fecha_cierre
 * @property bool $activo
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property bool $reabierto
 * @property string|null $motivo_reapertura
 * @property int|null $reabierto_por
 * @property Carbon|null $fecha_reapertura
 */
class TramiteCierre extends Model
{
    protected $fillable = [
        'tramite_id',
        'entrega_id',
        'cerrado_por',
        'resumen',
        'observacion',
        'fecha_cierre',
        'activo',
        'reabierto',
        'motivo_reapertura',
        'reabierto_por',
        'fecha_reapertura',
    ];

    protected function casts(): array
    {
        return [
            'fecha_cierre' => 'datetime',
            'activo' => 'boolean',
            'reabierto' => 'boolean',
            'fecha_reapertura' => 'datetime',
        ];
    }

    /** @return BelongsTo<Tramite, $this> */
    public function tramite(): BelongsTo
    {
        return $this->belongsTo(Tramite::class);
    }

    /** @return BelongsTo<TramiteEntrega, $this> */
    public function entrega(): BelongsTo
    {
        return $this->belongsTo(TramiteEntrega::class, 'entrega_id');
    }

    /** @return BelongsTo<User, $this> */
    public function cerradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cerrado_por');
    }

    /** @return HasOne<TramiteInformeCierre, $this> */
    public function informe(): HasOne
    {
        return $this->hasOne(TramiteInformeCierre::class, 'cierre_id');
    }
}
