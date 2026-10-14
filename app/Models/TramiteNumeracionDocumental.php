<?php

namespace App\Models;

use Database\Factories\TramiteNumeracionDocumentalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $serie_id
 * @property int $tramite_id
 * @property int $borrador_id
 * @property int $ronda_revision_id
 * @property int $anio
 * @property int $correlativo
 * @property string $numero_completo
 * @property string $estado
 * @property int|null $reservada_por
 * @property string|null $error_generacion
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
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

    /** @return BelongsTo<TramiteSerieDocumental, $this> */
    public function serie(): BelongsTo
    {
        return $this->belongsTo(TramiteSerieDocumental::class, 'serie_id');
    }

    /** @return BelongsTo<Tramite, $this> */
    public function tramite(): BelongsTo
    {
        return $this->belongsTo(Tramite::class);
    }

    /** @return BelongsTo<TramiteBorrador, $this> */
    public function borrador(): BelongsTo
    {
        return $this->belongsTo(TramiteBorrador::class, 'borrador_id');
    }

    /** @return BelongsTo<TramiteRondaRevision, $this> */
    public function rondaRevision(): BelongsTo
    {
        return $this->belongsTo(TramiteRondaRevision::class, 'ronda_revision_id');
    }

    /** @return HasOne<TramiteDocumentoFinal, $this> */
    public function documentoFinal(): HasOne
    {
        return $this->hasOne(TramiteDocumentoFinal::class, 'numeracion_id');
    }
}
