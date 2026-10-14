<?php

namespace App\Models;

use Database\Factories\TramiteDocumentoFinalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tramite_id
 * @property int $numeracion_id
 * @property int $borrador_id
 * @property int $ronda_revision_id
 * @property int $version
 * @property string $tipo_documento
 * @property string $numero_documento
 * @property string $codigo_verificacion
 * @property string $estado
 * @property bool $activo
 * @property string $disco
 * @property string|null $ruta
 * @property string|null $nombre_archivo
 * @property string $mime_type
 * @property string|null $sha256
 * @property int|null $tamano_bytes
 * @property int|null $numero_paginas
 * @property array<array-key, mixed> $contenido_snapshot
 * @property int|null $generado_por
 * @property Carbon|null $fecha_emision
 * @property string|null $error_generacion
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $documento_anterior_id
 * @property string|null $motivo_anulacion
 * @property Carbon|null $fecha_anulacion
 * @property int|null $anulado_por
 */
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

    /** @return BelongsTo<Tramite, $this> */
    public function tramite(): BelongsTo
    {
        return $this->belongsTo(Tramite::class);
    }

    /** @return BelongsTo<TramiteNumeracionDocumental, $this> */
    public function numeracion(): BelongsTo
    {
        return $this->belongsTo(TramiteNumeracionDocumental::class, 'numeracion_id');
    }

    /** @return BelongsTo<self, $this> */
    public function documentoAnterior(): BelongsTo
    {
        return $this->belongsTo(self::class, 'documento_anterior_id');
    }

    /** @return BelongsTo<TramiteBorrador, $this> */
    public function borrador(): BelongsTo
    {
        return $this->belongsTo(TramiteBorrador::class, 'borrador_id');
    }

    /** @return BelongsTo<TramiteRondaRevision, $this> */
    public function rondaRevision(): BelongsTo
    {
        return $this->belongsTo(TramiteRondaRevision::class, 'ronda_revision_id');
    }

    /** @return BelongsTo<User, $this> */
    public function generador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generado_por');
    }

    /** @return HasOne<TramiteFirma, $this> */
    public function firma(): HasOne
    {
        return $this->hasOne(TramiteFirma::class, 'documento_final_id');
    }

    /** @return HasOne<TramiteEntrega, $this> */
    public function entrega(): HasOne
    {
        return $this->hasOne(TramiteEntrega::class, 'documento_final_id');
    }
}
