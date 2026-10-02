<?php

namespace App\Models;

use Database\Factories\TramiteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
    'numero_expediente_externo',
    'area_procedencia',
    'persona_entrega_documento',
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

    public function recibidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recibido_por');
    }

    public function propietario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'propietario_id');
    }

    /** @return BelongsTo<ProgramaEstudio, $this> */
    public function programa(): BelongsTo
    {
        return $this->belongsTo(ProgramaEstudio::class, 'programa_estudio_id');
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(TramiteDocumento::class);
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(TramiteEvento::class);
    }

    public function borradores(): HasMany
    {
        return $this->hasMany(TramiteBorrador::class);
    }

    public function borradorActual(): HasOne
    {
        return $this->hasOne(TramiteBorrador::class)->where('es_actual', true);
    }

    public function asignaciones(): HasMany
    {
        return $this->hasMany(TramiteAsignacion::class);
    }

    public function asignacionActual(): HasOne
    {
        return $this->hasOne(TramiteAsignacion::class)->where('activa', true);
    }

    public function rondasRevision(): HasMany
    {
        return $this->hasMany(TramiteRondaRevision::class, 'tramite_id')->orderBy('numero_ronda');
    }

    public function documentosFinales(): HasMany
    {
        return $this->hasMany(TramiteDocumentoFinal::class, 'tramite_id')->orderByDesc('version');
    }

    public function documentoFinalActual(): HasOne
    {
        return $this->hasOne(TramiteDocumentoFinal::class, 'tramite_id')->where('activo', true);
    }

    public function firmas(): HasMany
    {
        return $this->hasMany(TramiteFirma::class, 'tramite_id')->orderByDesc('id');
    }

    public function entregas(): HasMany
    {
        return $this->hasMany(TramiteEntrega::class, 'tramite_id')->orderByDesc('id');
    }

    public function entregaActual(): HasOne
    {
        return $this->hasOne(TramiteEntrega::class, 'tramite_id')->where('activa', true);
    }

    public function cierre(): HasOne
    {
        return $this->hasOne(TramiteCierre::class, 'tramite_id')->where('activo', true);
    }

    public function informeCierre(): HasOne
    {
        return $this->hasOne(TramiteInformeCierre::class, 'tramite_id')->where('activo', true);
    }
}
