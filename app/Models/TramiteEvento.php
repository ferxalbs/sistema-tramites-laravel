<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tramite_id',
    'usuario_id',
    'accion',
    'descripcion',
    'estado_anterior',
    'estado_nuevo',
    'metadatos',
])]
class TramiteEvento extends Model
{
    protected function casts(): array
    {
        return [
            'metadatos' => 'array',
        ];
    }

    public function tramite(): BelongsTo
    {
        return $this->belongsTo(Tramite::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
