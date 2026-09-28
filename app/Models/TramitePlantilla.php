<?php

namespace App\Models;

use Database\Factories\TramitePlantillaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TramitePlantilla extends Model
{
    /** @use HasFactory<TramitePlantillaFactory> */
    use HasFactory;

    protected $fillable = [
        'codigo',
        'version',
        'nombre',
        'descripcion',
        'tipo_documento_salida',
        'modalidad',
        'contenido',
        'requiere_firma_fisica',
        'permite_no_firma',
        'estado',
        'activa',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'requiere_firma_fisica' => 'boolean',
            'permite_no_firma' => 'boolean',
            'activa' => 'boolean',
        ];
    }

    public function borradores(): HasMany
    {
        return $this->hasMany(TramiteBorrador::class, 'plantilla_id');
    }
}
