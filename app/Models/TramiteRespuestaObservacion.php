<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $observacion_id
 * @property int $borrador_id
 * @property int $asistente_id
 * @property string $respuesta
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class TramiteRespuestaObservacion extends Model
{
    protected $table = 'tramite_respuestas_observacion';

    protected $fillable = [
        'observacion_id',
        'borrador_id',
        'asistente_id',
        'respuesta',
    ];

    /** @return BelongsTo<TramiteObservacionRevision, $this> */
    public function observacion(): BelongsTo
    {
        return $this->belongsTo(TramiteObservacionRevision::class, 'observacion_id');
    }

    /** @return BelongsTo<TramiteBorrador, $this> */
    public function borrador(): BelongsTo
    {
        return $this->belongsTo(TramiteBorrador::class, 'borrador_id');
    }

    /** @return BelongsTo<User, $this> */
    public function asistente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asistente_id');
    }
}
