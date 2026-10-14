<?php

namespace App\Models;

use Database\Factories\TramiteSerieDocumentalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $tipo_documento_salida
 * @property string $modalidad
 * @property int $anio
 * @property string $codigo
 * @property string $prefijo
 * @property int $ultimo_correlativo
 * @property bool $activa
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class TramiteSerieDocumental extends Model
{
    protected $table = 'tramite_series_documentales';

    /** @use HasFactory<TramiteSerieDocumentalFactory> */
    use HasFactory;

    protected $fillable = [
        'tipo_documento_salida',
        'modalidad',
        'anio',
        'codigo',
        'prefijo',
        'ultimo_correlativo',
        'activa',
    ];

    protected function casts(): array
    {
        return [
            'anio' => 'integer',
            'ultimo_correlativo' => 'integer',
            'activa' => 'boolean',
        ];
    }

    /** @return HasMany<TramiteNumeracionDocumental, $this> */
    public function numeraciones(): HasMany
    {
        return $this->hasMany(TramiteNumeracionDocumental::class, 'serie_id');
    }
}
