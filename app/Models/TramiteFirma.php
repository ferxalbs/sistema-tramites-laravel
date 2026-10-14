<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tramite_id
 * @property int $documento_final_id
 * @property int|null $firmante_id
 * @property int|null $registrado_por
 * @property bool $no_requiere_firma
 * @property Carbon|null $fecha_firma
 * @property string|null $observacion
 * @property string|null $disco
 * @property string|null $ruta
 * @property string|null $nombre_original
 * @property string|null $mime_type
 * @property int|null $tamano_bytes
 * @property string|null $sha256
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class TramiteFirma extends Model
{
    protected $fillable = [
        'tramite_id',
        'documento_final_id',
        'firmante_id',
        'registrado_por',
        'no_requiere_firma',
        'fecha_firma',
        'observacion',
        'disco',
        'ruta',
        'nombre_original',
        'mime_type',
        'tamano_bytes',
        'sha256',
    ];

    protected function casts(): array
    {
        return [
            'no_requiere_firma' => 'boolean',
            'fecha_firma' => 'datetime',
            'tamano_bytes' => 'integer',
        ];
    }

    /** @return BelongsTo<Tramite, $this> */
    public function tramite(): BelongsTo
    {
        return $this->belongsTo(Tramite::class);
    }

    /** @return BelongsTo<TramiteDocumentoFinal, $this> */
    public function documentoFinal(): BelongsTo
    {
        return $this->belongsTo(TramiteDocumentoFinal::class, 'documento_final_id');
    }

    /** @return BelongsTo<User, $this> */
    public function firmante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'firmante_id');
    }

    /** @return BelongsTo<User, $this> */
    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }
}
