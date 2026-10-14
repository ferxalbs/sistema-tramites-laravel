<?php

namespace App\Models;

use Database\Factories\TramiteAsignacionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tramite_id
 * @property string $destino
 * @property int $revisor_id
 * @property string $rol_revisor
 * @property int|null $asignado_por
 * @property string $motivo
 * @property string|null $instrucciones_revision
 * @property Carbon|null $fecha_esperada
 * @property string $estado
 * @property bool $activa
 * @property Carbon|null $fecha_inicio_revision
 * @property Carbon|null $fecha_finalizacion
 * @property string|null $motivo_finalizacion
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class TramiteAsignacion extends Model
{
    /** @use HasFactory<TramiteAsignacionFactory> */
    use HasFactory;

    protected $table = 'tramite_asignaciones';

    protected $fillable = [
        'tramite_id',
        'destino',
        'revisor_id',
        'rol_revisor',
        'asignado_por',
        'motivo',
        'instrucciones_revision',
        'fecha_esperada',
        'estado',
        'activa',
        'fecha_inicio_revision',
        'fecha_finalizacion',
        'motivo_finalizacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha_esperada' => 'date',
            'activa' => 'boolean',
            'fecha_inicio_revision' => 'datetime',
            'fecha_finalizacion' => 'datetime',
        ];
    }

    /** @return BelongsTo<Tramite, $this> */
    public function tramite(): BelongsTo
    {
        return $this->belongsTo(Tramite::class);
    }

    /** @return BelongsTo<User, $this> */
    public function revisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisor_id');
    }

    /** @return BelongsTo<User, $this> */
    public function asignadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asignado_por');
    }

    /** @return HasMany<TramiteRondaRevision, $this> */
    public function rondasRevision(): HasMany
    {
        return $this->hasMany(TramiteRondaRevision::class, 'asignacion_id')->orderBy('numero_ronda');
    }
}
