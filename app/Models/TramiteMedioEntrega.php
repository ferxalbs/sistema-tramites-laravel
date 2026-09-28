<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TramiteMedioEntrega extends Model
{
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

    public function entregas(): HasMany
    {
        return $this->hasMany(TramiteEntrega::class, 'medio_entrega_id');
    }
}
