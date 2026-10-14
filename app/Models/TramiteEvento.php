<?php

namespace App\Models;

use App\Services\Tramites\CreateTramiteNotifications;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tramite_id
 * @property int|null $usuario_id
 * @property string $accion
 * @property string $descripcion
 * @property string|null $estado_anterior
 * @property string|null $estado_nuevo
 * @property array<array-key, mixed>|null $metadatos
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $clave_dedupe
 */
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
