<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tramite_id',
    'categoria',
    'disco',
    'ruta',
    'nombre_original',
    'mime_type',
    'tamano_bytes',
    'sha256',
    'version',
    'vigente',
    'documento_anterior_id',
    'cargado_por',
])]
class TramiteDocumento extends Model
{
    protected function casts(): array
    {
        return [
            'tamano_bytes' => 'integer',
            'version' => 'integer',
            'vigente' => 'boolean',
            'documento_anterior_id' => 'integer',
        ];
    }

    public function tramite(): BelongsTo
    {
        return $this->belongsTo(Tramite::class);
    }

    public function cargadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cargado_por');
    }
}
