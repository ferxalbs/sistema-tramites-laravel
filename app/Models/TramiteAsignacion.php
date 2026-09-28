<?php

namespace App\Models;

use Database\Factories\TramiteAsignacionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function tramite(): BelongsTo
    {
        return $this->belongsTo(Tramite::class);
    }

    public function revisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisor_id');
    }

    public function asignadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asignado_por');
    }

    public function rondasRevision(): HasMany
    {
        return $this->hasMany(TramiteRondaRevision::class, 'asignacion_id')->orderBy('numero_ronda');
    }
}
