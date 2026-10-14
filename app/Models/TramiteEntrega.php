<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tramite_id
 * @property int $documento_final_id
 * @property int $medio_entrega_id
 * @property int|null $entregado_por
 * @property int|null $receptor_usuario_id
 * @property string $receptor_nombre
 * @property string|null $receptor_documento
 * @property string $receptor_tipo
 * @property string|null $receptor_relacion
 * @property string|null $correo_destino
 * @property string|null $medio_utilizado
 * @property Carbon $fecha_entrega
 * @property bool $confirmado_por_estudiante
 * @property bool $confirmado
 * @property string $codigo_confirmacion
 * @property string|null $observaciones
 * @property string $estado
 * @property bool $activa
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class TramiteEntrega extends Model
{
    protected $fillable = [
        'tramite_id',
        'documento_final_id',
        'medio_entrega_id',
        'entregado_por',
        'receptor_usuario_id',
        'receptor_nombre',
        'receptor_documento',
        'receptor_tipo',
        'receptor_relacion',
        'correo_destino',
        'medio_utilizado',
        'fecha_entrega',
        'confirmado_por_estudiante',
        'confirmado',
        'codigo_confirmacion',
        'observaciones',
        'estado',
        'activa',
    ];

    protected function casts(): array
    {
        return [
            'fecha_entrega' => 'datetime',
            'confirmado_por_estudiante' => 'boolean',
            'confirmado' => 'boolean',
            'activa' => 'boolean',
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

    /** @return BelongsTo<TramiteMedioEntrega, $this> */
    public function medio(): BelongsTo
    {
        return $this->belongsTo(TramiteMedioEntrega::class, 'medio_entrega_id');
    }

    /** @return BelongsTo<User, $this> */
    public function entregadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entregado_por');
    }

    /** @return BelongsTo<User, $this> */
    public function receptor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receptor_usuario_id');
    }

    /** @return HasMany<TramiteEvidenciaEntrega, $this> */
    public function evidencias(): HasMany
    {
        return $this->hasMany(TramiteEvidenciaEntrega::class, 'entrega_id')->where('activa', true);
    }
}
