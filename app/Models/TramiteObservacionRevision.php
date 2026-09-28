<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TramiteObservacionRevision extends Model
{
    public const CATEGORIAS = [
        'Datos personales',
        'Contenido',
        'Destinatarios',
        'Personas mencionadas',
        'Fechas',
        'Documentación adjunta',
        'Formato',
        'Firma propuesta',
        'Otro',
    ];

    protected $table = 'tramite_observaciones_revision';

    protected $fillable = [
        'ronda_id',
        'tramite_id',
        'revisor_id',
        'categoria',
        'titulo',
        'descripcion',
        'seccion',
        'obligatoria',
        'visible_para_interesado',
        'orden',
    ];

    protected function casts(): array
    {
        return [
            'obligatoria' => 'boolean',
            'visible_para_interesado' => 'boolean',
            'orden' => 'integer',
        ];
    }

    public function ronda(): BelongsTo
    {
        return $this->belongsTo(TramiteRondaRevision::class, 'ronda_id');
    }

    public function revisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisor_id');
    }

    public function respuesta(): HasOne
    {
        return $this->hasOne(TramiteRespuestaObservacion::class, 'observacion_id');
    }
}
