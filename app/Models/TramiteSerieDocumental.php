<?php

namespace App\Models;

use Database\Factories\TramiteSerieDocumentalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function numeraciones(): HasMany
    {
        return $this->hasMany(TramiteNumeracionDocumental::class, 'serie_id');
    }
}
