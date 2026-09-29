<?php

namespace App\Models;

use Database\Factories\ProgramaEstudioFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $codigo
 * @property string $nombre
 * @property bool $activo
 */
#[Fillable(['codigo', 'nombre', 'descripcion', 'activo'])]
class ProgramaEstudio extends Model
{
    /** @use HasFactory<ProgramaEstudioFactory> */
    use HasFactory;

    protected $table = 'programas_estudio';

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    /** @return HasMany<PerfilEstudiante, $this> */
    public function perfilesEstudiante(): HasMany
    {
        return $this->hasMany(PerfilEstudiante::class);
    }
}
