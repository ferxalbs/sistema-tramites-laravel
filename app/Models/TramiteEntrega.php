<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function tramite(): BelongsTo
    {
        return $this->belongsTo(Tramite::class);
    }

    public function documentoFinal(): BelongsTo
    {
        return $this->belongsTo(TramiteDocumentoFinal::class, 'documento_final_id');
    }

    public function medio(): BelongsTo
    {
        return $this->belongsTo(TramiteMedioEntrega::class, 'medio_entrega_id');
    }

    public function entregadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entregado_por');
    }

    public function receptor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receptor_usuario_id');
    }

    public function evidencias(): HasMany
    {
        return $this->hasMany(TramiteEvidenciaEntrega::class, 'entrega_id')->where('activa', true);
    }
}
