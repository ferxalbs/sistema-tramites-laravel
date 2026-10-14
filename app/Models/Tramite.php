<?php

namespace App\Models;

use Database\Factories\TramiteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $codigo
 * @property string $clasificacion
 * @property string $tipo_documento
 * @property string|null $persona_nombre
 * @property string|null $persona_identificador
 * @property string $destino_tipo
 * @property string $destino_nombre
 * @property string $asunto
 * @property string|null $descripcion
 * @property string $prioridad
 * @property Carbon $fecha_recepcion
 * @property int|null $folios
 * @property string $estado
 * @property int|null $recibido_por
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $propietario_id
 * @property Carbon|null $fecha_llegada_oficina
 * @property Carbon|null $fecha_presentacion_original
 * @property string|null $numero_expediente_externo
 * @property string|null $area_procedencia
 * @property string|null $persona_entrega_documento
 * @property string|null $observacion_recepcion
 * @property int|null $programa_estudio_id
 * @property string|null $formato_salida
 * @property string|null $modalidad_documento
 * @property int|null $destino_docente_id
 * @property string|null $solicitante_correo
 * @property string|null $solicitante_celular
 */
#[Fillable([
    'codigo',
    'clasificacion',
    'tipo_documento',
    'formato_salida',
    'modalidad_documento',
    'persona_nombre',
    'persona_identificador',
    'solicitante_correo',
    'solicitante_celular',
    'propietario_id',
    'programa_estudio_id',
    'destino_tipo',
    'destino_nombre',
    'destino_docente_id',
    'asunto',
    'descripcion',
    'prioridad',
    'fecha_recepcion',
    'fecha_llegada_oficina',
    'fecha_presentacion_original',
    'observacion_recepcion',
    'folios',
    'estado',
    'recibido_por',
])]
class Tramite extends Model
{
    /** @use HasFactory<TramiteFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'fecha_recepcion' => 'date',
            'fecha_llegada_oficina' => 'datetime',
            'fecha_presentacion_original' => 'date',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function recibidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recibido_por');
    }

    /** @return BelongsTo<User, $this> */
    public function propietario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'propietario_id');
    }

    /** @return BelongsTo<ProgramaEstudio, $this> */
    public function programa(): BelongsTo
    {
        return $this->belongsTo(ProgramaEstudio::class, 'programa_estudio_id');
    }

    /** @return HasMany<TramiteDocumento, $this> */
    public function documentos(): HasMany
    {
        return $this->hasMany(TramiteDocumento::class);
    }

    /** @return HasMany<TramiteEvento, $this> */
    public function eventos(): HasMany
    {
        return $this->hasMany(TramiteEvento::class);
    }

    /** @return HasMany<TramiteBorrador, $this> */
    public function borradores(): HasMany
    {
        return $this->hasMany(TramiteBorrador::class);
    }

    /** @return HasOne<TramiteBorrador, $this> */
    public function borradorActual(): HasOne
    {
        return $this->hasOne(TramiteBorrador::class)->where('es_actual', true);
    }

    /** @return HasMany<TramiteAsignacion, $this> */
    public function asignaciones(): HasMany
    {
        return $this->hasMany(TramiteAsignacion::class);
    }

    /** @return HasOne<TramiteAsignacion, $this> */
    public function asignacionActual(): HasOne
    {
        return $this->hasOne(TramiteAsignacion::class)->where('activa', true);
    }

    /** @return HasMany<TramiteRondaRevision, $this> */
    public function rondasRevision(): HasMany
    {
        return $this->hasMany(TramiteRondaRevision::class, 'tramite_id')->orderBy('numero_ronda');
    }

    /** @return HasMany<TramiteDocumentoFinal, $this> */
    public function documentosFinales(): HasMany
    {
        return $this->hasMany(TramiteDocumentoFinal::class, 'tramite_id')->orderByDesc('version');
    }

    /** @return HasOne<TramiteDocumentoFinal, $this> */
    public function documentoFinalActual(): HasOne
    {
        return $this->hasOne(TramiteDocumentoFinal::class, 'tramite_id')->where('activo', true);
    }

    /** @return HasMany<TramiteFirma, $this> */
    public function firmas(): HasMany
    {
        return $this->hasMany(TramiteFirma::class, 'tramite_id')->orderByDesc('id');
    }

    /** @return HasMany<TramiteEntrega, $this> */
    public function entregas(): HasMany
    {
        return $this->hasMany(TramiteEntrega::class, 'tramite_id')->orderByDesc('id');
    }

    /** @return HasOne<TramiteEntrega, $this> */
    public function entregaActual(): HasOne
    {
        return $this->hasOne(TramiteEntrega::class, 'tramite_id')->where('activa', true);
    }

    /** @return HasOne<TramiteCierre, $this> */
    public function cierre(): HasOne
    {
        return $this->hasOne(TramiteCierre::class, 'tramite_id')->where('activo', true);
    }

    /** @return HasOne<TramiteInformeCierre, $this> */
    public function informeCierre(): HasOne
    {
        return $this->hasOne(TramiteInformeCierre::class, 'tramite_id')->where('activo', true);
    }
}
