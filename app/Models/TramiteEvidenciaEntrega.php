<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $entrega_id
 * @property int $tramite_id
 * @property int $documento_final_id
 * @property string $tipo_evidencia
 * @property string|null $nombre_original
 * @property string|null $disco
 * @property string|null $ruta
 * @property string|null $mime_type
 * @property int|null $tamano_bytes
 * @property string|null $sha256
 * @property string|null $codigo_confirmacion
 * @property int|null $registrado_por
 * @property string|null $observacion
 * @property bool $activa
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class TramiteEvidenciaEntrega extends Model
{
    protected $table = 'tramite_evidencias_entrega';

    protected $fillable = [
        'entrega_id',
        'tramite_id',
        'documento_final_id',
        'tipo_evidencia',
        'nombre_original',
        'disco',
        'ruta',
        'mime_type',
        'tamano_bytes',
        'sha256',
        'codigo_confirmacion',
        'registrado_por',
        'observacion',
        'activa',
    ];

    protected function casts(): array
    {
        return [
            'tamano_bytes' => 'integer',
            'activa' => 'boolean',
        ];
    }

    /** @return BelongsTo<TramiteEntrega, $this> */
    public function entrega(): BelongsTo
    {
        return $this->belongsTo(TramiteEntrega::class, 'entrega_id');
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
    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }
}
