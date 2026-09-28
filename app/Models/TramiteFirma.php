<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TramiteFirma extends Model
{
    protected $fillable = [
        'tramite_id',
        'documento_final_id',
        'firmante_id',
        'registrado_por',
        'no_requiere_firma',
        'fecha_firma',
        'observacion',
        'disco',
        'ruta',
        'nombre_original',
        'mime_type',
        'tamano_bytes',
        'sha256',
    ];

    protected function casts(): array
    {
        return [
            'no_requiere_firma' => 'boolean',
            'fecha_firma' => 'datetime',
            'tamano_bytes' => 'integer',
        ];
    }

    public function tramite(): BelongsTo
    {
        return $this->belongsTo(Tramite::class);
    }

    public function documentoFinal(): BelongsTo
    {
        return $this->belongsTo(TramiteDocumentoFinal::class, 'documento_final_id');
    }

    public function firmante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'firmante_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }
}
