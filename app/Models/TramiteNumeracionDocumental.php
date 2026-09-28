<?php

namespace App\Models;

use Database\Factories\TramiteNumeracionDocumentalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TramiteNumeracionDocumental extends Model
{
    protected $table = 'tramite_numeraciones_documentales';

    /** @use HasFactory<TramiteNumeracionDocumentalFactory> */
    use HasFactory;

    protected $fillable = [
        'serie_id',
        'tramite_id',
        'borrador_id',
        'ronda_revision_id',
        'anio',
        'correlativo',
        'numero_completo',
        'estado',
        'reservada_por',
        'error_generacion',
    ];

    protected function casts(): array
    {
        return [
            'anio' => 'integer',
            'correlativo' => 'integer',
        ];
    }

    public function serie(): BelongsTo
    {
        return $this->belongsTo(TramiteSerieDocumental::class, 'serie_id');
    }

    public function tramite(): BelongsTo
    {
        return $this->belongsTo(Tramite::class);
    }

    public function borrador(): BelongsTo
    {
        return $this->belongsTo(TramiteBorrador::class, 'borrador_id');
    }

    public function rondaRevision(): BelongsTo
    {
        return $this->belongsTo(TramiteRondaRevision::class, 'ronda_revision_id');
    }

    public function documentoFinal(): HasOne
    {
        return $this->hasOne(TramiteDocumentoFinal::class, 'numeracion_id');
    }
}
