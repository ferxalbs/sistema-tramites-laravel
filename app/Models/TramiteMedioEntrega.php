<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $codigo
 * @property string $nombre
 * @property string $tipo
 * @property bool $requiere_evidencia
 * @property bool $activo
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class TramiteMedioEntrega extends Model
{
    public const CODIGOS_DISPONIBLES = [
        'presencial',
        'correo_electronico',
        'descarga_sistema',
    ];

    protected $table = 'tramite_medios_entrega';

    protected $fillable = [
        'codigo',
        'nombre',
        'tipo',
        'requiere_evidencia',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'requiere_evidencia' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    /** @return HasMany<TramiteEntrega, $this> */
    public function entregas(): HasMany
    {
        return $this->hasMany(TramiteEntrega::class, 'medio_entrega_id');
    }
}
