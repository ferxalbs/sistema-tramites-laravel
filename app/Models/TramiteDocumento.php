<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tramite_id
 * @property string $categoria
 * @property string $disco
 * @property string $ruta
 * @property string $nombre_original
 * @property string $mime_type
 * @property int $tamano_bytes
 * @property string $sha256
 * @property int $version
 * @property bool $vigente
 * @property int|null $cargado_por
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $documento_anterior_id
 */
#[Fillable([
    'tramite_id',
    'categoria',
    'disco',
    'ruta',
    'nombre_original',
    'mime_type',
    'tamano_bytes',
    'sha256',
    'version',
    'vigente',
    'documento_anterior_id',
    'cargado_por',
])]
class TramiteDocumento extends Model
{
    protected function casts(): array
    {
        return [
            'tamano_bytes' => 'integer',
            'version' => 'integer',
            'vigente' => 'boolean',
            'documento_anterior_id' => 'integer',
        ];
    }

    /** @return BelongsTo<Tramite, $this> */
    public function tramite(): BelongsTo
    {
        return $this->belongsTo(Tramite::class);
    }

    /** @return BelongsTo<User, $this> */
    public function cargadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cargado_por');
    }
}
