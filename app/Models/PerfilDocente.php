<?php

namespace App\Models;

use Database\Factories\PerfilDocenteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $programa_estudio_id
 * @property string|null $codigo_docente
 * @property string|null $especialidad
 * @property string|null $condicion_laboral
 */
#[Fillable(['user_id', 'programa_estudio_id', 'codigo_docente', 'especialidad', 'condicion_laboral'])]
class PerfilDocente extends Model
{
    /** @use HasFactory<PerfilDocenteFactory> */
    use HasFactory;

    protected $table = 'perfiles_docente';

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
