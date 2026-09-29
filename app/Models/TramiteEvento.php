<?php

namespace App\Models;

use App\Services\Tramites\CreateTramiteNotifications;
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
    'clave_dedupe',
])]
class TramiteEvento extends Model
{
    protected static function booted(): void
    {
        static::created(function (self $evento): void {
            app(CreateTramiteNotifications::class)->forEvent($evento);
        });
    }

    protected function casts(): array
    {
        return [
            'metadatos' => 'array',
        ];
    }

    /** @return BelongsTo<Tramite, $this> */
    public function tramite(): BelongsTo
    {
        return $this->belongsTo(Tramite::class);
    }

    /** @return BelongsTo<User, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
