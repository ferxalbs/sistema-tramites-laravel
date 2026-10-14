<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tramite_id
 * @property int $cierre_id
 * @property int|null $generado_por
 * @property string $disco
 * @property string $ruta
 * @property string $nombre_archivo
 * @property string $sha256
 * @property int $tamano_bytes
 * @property int $numero_paginas
 * @property string $codigo_verificacion
 * @property bool $activo
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class TramiteInformeCierre extends Model
{
    protected $table = 'tramite_informes_cierre';

    protected $fillable = [
        'tramite_id',
        'cierre_id',
        'generado_por',
        'disco',
        'ruta',
        'nombre_archivo',
        'sha256',
        'tamano_bytes',
        'numero_paginas',
        'codigo_verificacion',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'tamano_bytes' => 'integer',
            'numero_paginas' => 'integer',
            'activo' => 'boolean',
        ];
    }

    /** @return BelongsTo<Tramite, $this> */
    public function tramite(): BelongsTo
    {
        return $this->belongsTo(Tramite::class);
    }

    /** @return BelongsTo<TramiteCierre, $this> */
    public function cierre(): BelongsTo
    {
        return $this->belongsTo(TramiteCierre::class, 'cierre_id');
    }

    /** @return BelongsTo<User, $this> */
    public function generador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generado_por');
    }
}
