<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $ronda_id
 * @property int $tramite_id
 * @property int $revisor_id
 * @property string $categoria
 * @property string $titulo
 * @property string $descripcion
 * @property string|null $seccion
 * @property bool $obligatoria
 * @property bool $visible_para_interesado
 * @property int $orden
 * @property-read TramiteRespuestaObservacion|null $respuesta
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
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

    /** @return BelongsTo<TramiteRondaRevision, $this> */
    public function ronda(): BelongsTo
    {
        return $this->belongsTo(TramiteRondaRevision::class, 'ronda_id');
    }

    /** @return BelongsTo<User, $this> */
    public function revisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisor_id');
    }

    /** @return HasOne<TramiteRespuestaObservacion, $this> */
    public function respuesta(): HasOne
    {
        return $this->hasOne(TramiteRespuestaObservacion::class, 'observacion_id');
    }
}
