<?php

namespace App\Models;

use Database\Factories\PerfilEstudianteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $programa_estudio_id
 * @property string|null $codigo_estudiante
 * @property string $condicion_academica
 * @property int|null $ciclo_actual
 * @property int|null $anio_egreso
 * @property string|null $direccion_residencia
 */
#[Fillable(['user_id', 'programa_estudio_id', 'codigo_estudiante', 'condicion_academica', 'ciclo_actual', 'anio_egreso', 'direccion_residencia'])]
class PerfilEstudiante extends Model
{
    /** @use HasFactory<PerfilEstudianteFactory> */
    use HasFactory;

    protected $table = 'perfiles_estudiante';

    protected function casts(): array
    {
        return ['ciclo_actual' => 'integer', 'anio_egreso' => 'integer'];
    }

    /** @return BelongsTo<User, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<ProgramaEstudio, $this> */
    public function programa(): BelongsTo
    {
        return $this->belongsTo(ProgramaEstudio::class, 'programa_estudio_id');
    }
}
