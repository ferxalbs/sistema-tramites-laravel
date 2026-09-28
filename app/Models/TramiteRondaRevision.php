<?php

namespace App\Models;

use Database\Factories\TramiteRondaRevisionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function tramite(): BelongsTo
    {
        return $this->belongsTo(Tramite::class);
    }

    public function asignacion(): BelongsTo
    {
        return $this->belongsTo(TramiteAsignacion::class, 'asignacion_id');
    }

    public function revisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisor_id');
    }

    public function versionBorrador(): BelongsTo
    {
        return $this->belongsTo(TramiteBorrador::class, 'borrador_id');
    }

    public function observaciones(): HasMany
    {
        return $this->hasMany(TramiteObservacionRevision::class, 'ronda_id')->orderBy('orden');
    }
}
