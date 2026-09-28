<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function entrega(): BelongsTo
    {
        return $this->belongsTo(TramiteEntrega::class, 'entrega_id');
    }

    public function tramite(): BelongsTo
    {
        return $this->belongsTo(Tramite::class);
    }

    public function documentoFinal(): BelongsTo
    {
        return $this->belongsTo(TramiteDocumentoFinal::class, 'documento_final_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }
}
