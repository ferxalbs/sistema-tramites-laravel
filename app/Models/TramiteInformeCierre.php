<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TramiteInformeCierre extends Model
{
    protected $table = 'tramite_informes_cierre';

    protected $fillable = [
        'tramite_id',
        'cierre_id',
        'generado_por',
        'disco',
        'ruta',
        'nombre_archivo',
        'sha256',
        'tamano_bytes',
        'numero_paginas',
        'codigo_verificacion',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'tamano_bytes' => 'integer',
            'numero_paginas' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function tramite(): BelongsTo
    {
        return $this->belongsTo(Tramite::class);
    }

    public function cierre(): BelongsTo
    {
        return $this->belongsTo(TramiteCierre::class, 'cierre_id');
    }

    public function generador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generado_por');
    }
}
