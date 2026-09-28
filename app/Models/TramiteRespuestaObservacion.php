<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TramiteRespuestaObservacion extends Model
{
    protected $table = 'tramite_respuestas_observacion';

    protected $fillable = [
        'observacion_id',
        'borrador_id',
        'asistente_id',
        'respuesta',
    ];

    public function observacion(): BelongsTo
    {
        return $this->belongsTo(TramiteObservacionRevision::class, 'observacion_id');
    }

    public function borrador(): BelongsTo
    {
        return $this->belongsTo(TramiteBorrador::class, 'borrador_id');
    }

    public function asistente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asistente_id');
    }
}
