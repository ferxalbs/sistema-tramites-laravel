<?php

namespace App\Models;

use Database\Factories\TramiteRondaRevisionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tramite_id
 * @property int $asignacion_id
 * @property int $numero_ronda
 * @property int $revisor_id
 * @property int $borrador_id
 * @property string $estado
 * @property bool $activa
 * @property Carbon $iniciada_en
 * @property Carbon|null $cerrada_en
 * @property string|null $resumen_observacion
 * @property string|null $resumen_correccion
 * @property string|null $conclusion
 * @property string|null $comentario_publico
 * @property string|null $comentario_interno
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class TramiteRondaRevision extends Model
{
    /** @use HasFactory<TramiteRondaRevisionFactory> */
    use HasFactory;

    protected $table = 'tramite_rondas_revision';

    protected $fillable = [
        'tramite_id',
        'asignacion_id',
        'numero_ronda',
        'revisor_id',
        'borrador_id',
        'estado',
        'activa',
        'iniciada_en',
        'cerrada_en',
        'resumen_observacion',
        'resumen_correccion',
        'conclusion',
        'comentario_publico',
        'comentario_interno',
    ];

    protected function casts(): array
    {
        return [
            'numero_ronda' => 'integer',
            'activa' => 'boolean',
            'iniciada_en' => 'datetime',
            'cerrada_en' => 'datetime',
        ];
    }

    /** @return BelongsTo<Tramite, $this> */
    public function tramite(): BelongsTo
    {
        return $this->belongsTo(Tramite::class);
    }

    /** @return BelongsTo<TramiteAsignacion, $this> */
    public function asignacion(): BelongsTo
    {
        return $this->belongsTo(TramiteAsignacion::class, 'asignacion_id');
    }

    /** @return BelongsTo<User, $this> */
    public function revisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisor_id');
    }

    /** @return BelongsTo<TramiteBorrador, $this> */
    public function versionBorrador(): BelongsTo
    {
        return $this->belongsTo(TramiteBorrador::class, 'borrador_id');
    }

    /** @return HasMany<TramiteObservacionRevision, $this> */
    public function observaciones(): HasMany
    {
        return $this->hasMany(TramiteObservacionRevision::class, 'ronda_id')->orderBy('orden');
    }
}
