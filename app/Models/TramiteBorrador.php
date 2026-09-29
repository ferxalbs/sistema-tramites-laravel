<?php

namespace App\Models;

use Database\Factories\TramiteBorradorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function tramite(): BelongsTo
    {
        return $this->belongsTo(Tramite::class);
    }

    public function plantilla(): BelongsTo
    {
        return $this->belongsTo(TramitePlantilla::class, 'plantilla_id');
    }

    public function remitente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'remitente_id');
    }

    public function firmante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'firmante_id');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function respuestasObservacion(): HasMany
    {
        return $this->hasMany(TramiteRespuestaObservacion::class, 'borrador_id');
    }
}
