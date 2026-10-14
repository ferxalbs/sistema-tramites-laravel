<?php

namespace App\Models;

use Database\Factories\TramiteBorradorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tramite_id
 * @property int $plantilla_id
 * @property int $version_plantilla
 * @property int $version
 * @property int|null $remitente_id
 * @property int|null $firmante_id
 * @property Carbon $fecha_documento
 * @property string $lugar
 * @property string $asunto
 * @property string|null $introduccion
 * @property string|null $contenido_principal
 * @property string|null $cierre
 * @property array<array-key, mixed>|null $destinatarios
 * @property array<array-key, mixed>|null $personas_mencionadas
 * @property array<array-key, mixed>|null $adjuntos
 * @property string $estado
 * @property bool $es_actual
 * @property Carbon|null $preparado_en
 * @property int|null $creado_por
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $contenido_plantilla_snapshot
 * @property string|null $contenido_renderizado
 */
class TramiteBorrador extends Model
{
    /** @use HasFactory<TramiteBorradorFactory> */
    use HasFactory;

    protected $table = 'tramite_borradores';

    protected $fillable = [
        'tramite_id',
        'plantilla_id',
        'version_plantilla',
        'contenido_plantilla_snapshot',
        'contenido_renderizado',
        'version',
        'remitente_id',
        'firmante_id',
        'fecha_documento',
        'lugar',
        'asunto',
        'introduccion',
        'contenido_principal',
        'cierre',
        'destinatarios',
        'personas_mencionadas',
        'adjuntos',
        'estado',
        'es_actual',
        'preparado_en',
        'creado_por',
    ];

    protected function casts(): array
    {
        return [
            'version_plantilla' => 'integer',
            'version' => 'integer',
            'fecha_documento' => 'date',
            'destinatarios' => 'array',
            'personas_mencionadas' => 'array',
            'adjuntos' => 'array',
            'es_actual' => 'boolean',
            'preparado_en' => 'datetime',
        ];
    }

    /** @return BelongsTo<Tramite, $this> */
    public function tramite(): BelongsTo
    {
        return $this->belongsTo(Tramite::class);
    }

    /** @return BelongsTo<TramitePlantilla, $this> */
    public function plantilla(): BelongsTo
    {
        return $this->belongsTo(TramitePlantilla::class, 'plantilla_id');
    }

    /** @return BelongsTo<User, $this> */
    public function remitente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'remitente_id');
    }

    /** @return BelongsTo<User, $this> */
    public function firmante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'firmante_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    /** @return HasMany<TramiteRespuestaObservacion, $this> */
    public function respuestasObservacion(): HasMany
    {
        return $this->hasMany(TramiteRespuestaObservacion::class, 'borrador_id');
    }
}
