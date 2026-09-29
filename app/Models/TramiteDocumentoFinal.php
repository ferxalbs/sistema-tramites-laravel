<?php

namespace App\Models;

use Database\Factories\TramiteDocumentoFinalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TramiteDocumentoFinal extends Model
{
    protected $table = 'tramite_documentos_finales';

    /** @use HasFactory<TramiteDocumentoFinalFactory> */
    use HasFactory;

    protected $fillable = [
        'tramite_id',
        'numeracion_id',
        'documento_anterior_id',
        'borrador_id',
        'ronda_revision_id',
        'version',
        'tipo_documento',
        'numero_documento',
        'codigo_verificacion',
        'estado',
        'activo',
        'disco',
        'ruta',
        'nombre_archivo',
        'mime_type',
        'sha256',
        'tamano_bytes',
        'numero_paginas',
        'contenido_snapshot',
        'generado_por',
        'fecha_emision',
        'error_generacion',
        'motivo_anulacion',
        'fecha_anulacion',
        'anulado_por',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'activo' => 'boolean',
            'tamano_bytes' => 'integer',
            'numero_paginas' => 'integer',
            'contenido_snapshot' => 'array',
            'fecha_emision' => 'datetime',
            'fecha_anulacion' => 'datetime',
        ];
    }

    public function tramite(): BelongsTo
    {
        return $this->belongsTo(Tramite::class);
    }

    public function numeracion(): BelongsTo
    {
        return $this->belongsTo(TramiteNumeracionDocumental::class, 'numeracion_id');
    }

    /** @return BelongsTo<self, $this> */
    public function documentoAnterior(): BelongsTo
    {
        return $this->belongsTo(self::class, 'documento_anterior_id');
    }

    public function borrador(): BelongsTo
    {
        return $this->belongsTo(TramiteBorrador::class, 'borrador_id');
    }

    public function rondaRevision(): BelongsTo
    {
        return $this->belongsTo(TramiteRondaRevision::class, 'ronda_revision_id');
    }

    public function generador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generado_por');
    }

    public function firma(): HasOne
    {
        return $this->hasOne(TramiteFirma::class, 'documento_final_id');
    }

    public function entrega(): HasOne
    {
        return $this->hasOne(TramiteEntrega::class, 'documento_final_id');
    }
}
