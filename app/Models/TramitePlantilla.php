<?php

namespace App\Models;

use Database\Factories\TramitePlantillaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $codigo
 * @property int $version
 * @property string $nombre
 * @property string|null $descripcion
 * @property string $tipo_documento_salida
 * @property string|null $modalidad
 * @property string $contenido
 * @property bool $requiere_firma_fisica
 * @property bool $permite_no_firma
 * @property string $estado
 * @property bool $activa
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $tipo_tramite
 * @property Carbon|null $validada_en
 * @property int|null $validada_por
 * @property string|null $fuente_modelo
 * @property bool $modelo_propuesto
 */
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
        'tipo_tramite',
        'validada_en',
        'validada_por',
        'fuente_modelo',
        'modelo_propuesto',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'requiere_firma_fisica' => 'boolean',
            'permite_no_firma' => 'boolean',
            'activa' => 'boolean',
            'modelo_propuesto' => 'boolean',
            'validada_en' => 'datetime',
        ];
    }

    /** @return HasMany<TramiteBorrador, $this> */
    public function borradores(): HasMany
    {
        return $this->hasMany(TramiteBorrador::class, 'plantilla_id');
    }
}
