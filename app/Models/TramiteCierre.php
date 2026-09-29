<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    public function tramite(): BelongsTo
    {
        return $this->belongsTo(Tramite::class);
    }

    public function entrega(): BelongsTo
    {
        return $this->belongsTo(TramiteEntrega::class, 'entrega_id');
    }

    public function cerradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cerrado_por');
    }

    public function informe(): HasOne
    {
        return $this->hasOne(TramiteInformeCierre::class, 'cierre_id');
    }
}
