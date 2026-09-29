<?php

namespace App\Http\Controllers;

use App\Models\Tramite;
use App\Models\TramiteDocumentoFinal;
use App\Models\User;
use App\Services\Tramites\TramiteTypeCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class GlobalSearchController extends Controller
{
    public function __invoke(Request $request): InertiaResponse
    {
        $input = $request->validate(['q' => ['nullable', 'string', 'max:500']]);
        $query = preg_replace('/\s+/u', ' ', trim($input['q'] ?? '')) ?? '';
        $status = match (true) {
            $query === '' => 'empty',
            mb_strlen($query) < 2 => 'short',
            mb_strlen($query) > 80 => 'too_long',
            default => 'ok',
        };
        $results = ['expedientes' => [], 'personas' => [], 'documentos' => []];

        if ($status === 'ok') {
            $typeLabels = TramiteTypeCatalog::labels();
            $actor = $request->user();
            $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $query).'%';
            $isReviewer = $actor->rol === 'docente';

            $results['expedientes'] = Tramite::query()
                ->when($isReviewer, fn (Builder $builder): Builder => $builder->whereHas('asignaciones', fn (Builder $assignment): Builder => $assignment
                    ->where('revisor_id', $actor->id)
                    ->where('destino', 'docente')
                    ->where(fn (Builder $assignment): Builder => $assignment->where('activa', true)
                        ->orWhereIn('estado', ['aprobado', 'rechazado']))))
                ->where(function (Builder $builder) use ($like, $isReviewer): void {
                    $builder->whereRaw("codigo LIKE ? ESCAPE '!'", [$like])
                        ->orWhereRaw("numero_expediente_externo LIKE ? ESCAPE '!'", [$like])
                        ->orWhereRaw("asunto LIKE ? ESCAPE '!'", [$like])
                        ->orWhereRaw("tipo_documento LIKE ? ESCAPE '!'", [$like]);

                    if (! $isReviewer) {
                        $builder->orWhereRaw("persona_nombre LIKE ? ESCAPE '!'", [$like])
                            ->orWhereRaw("persona_identificador LIKE ? ESCAPE '!'", [$like])
                            ->orWhereHas('propietario', fn (Builder $owner): Builder => $owner
                                ->whereRaw("name LIKE ? ESCAPE '!'", [$like])
                                ->orWhereRaw("nombres LIKE ? ESCAPE '!'", [$like])
                                ->orWhereRaw("apellidos LIKE ? ESCAPE '!'", [$like])
                                ->orWhereRaw("dni LIKE ? ESCAPE '!'", [$like])
                                ->orWhereRaw("email LIKE ? ESCAPE '!'", [$like]));
                    }
                })
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->limit(12)
                ->get(['id', 'codigo', 'asunto', 'estado', 'clasificacion', 'tipo_documento'])
                ->map(fn (Tramite $tramite): array => [
                    'id' => $tramite->id,
                    'codigo' => $tramite->codigo,
                    'asunto' => $tramite->asunto,
                    'estado' => config('tramites.estados.'.$tramite->estado, $tramite->estado),
                    'tipo_documento' => $typeLabels[$tramite->tipo_documento] ?? $tramite->tipo_documento,
                ])->all();

            if (! $isReviewer) {
                $results['personas'] = User::query()
                    ->when($actor->rol === 'asistente', fn (Builder $builder): Builder => $builder->where('rol', 'estudiante'))
                    ->where(fn (Builder $builder): Builder => $builder
                        ->whereRaw("name LIKE ? ESCAPE '!'", [$like])
                        ->orWhereRaw("nombres LIKE ? ESCAPE '!'", [$like])
                        ->orWhereRaw("apellidos LIKE ? ESCAPE '!'", [$like])
                        ->orWhereRaw("dni LIKE ? ESCAPE '!'", [$like])
                        ->orWhereRaw("email LIKE ? ESCAPE '!'", [$like])
                        ->orWhereHas('perfilEstudiante', fn (Builder $profile): Builder => $profile->whereRaw("codigo_estudiante LIKE ? ESCAPE '!'", [$like]))
                        ->orWhereHas('perfilDocente', fn (Builder $profile): Builder => $profile->whereRaw("codigo_docente LIKE ? ESCAPE '!'", [$like])))
                    ->orderBy('name')
                    ->orderBy('id')
                    ->limit(12)
                    ->get(['id', 'name', 'rol'])
                    ->map(fn (User $person): array => ['id' => $person->id, 'name' => $person->name, 'rol' => $person->rol])
                    ->all();

                $results['documentos'] = TramiteDocumentoFinal::query()
                    ->where('estado', 'emitido')
                    ->where('activo', true)
                    ->where(fn (Builder $builder): Builder => $builder
                        ->whereRaw("numero_documento LIKE ? ESCAPE '!'", [$like])
                        ->orWhereRaw("codigo_verificacion LIKE ? ESCAPE '!'", [$like])
                        ->orWhereHas('tramite', fn (Builder $tramite): Builder => $tramite->whereRaw("codigo LIKE ? ESCAPE '!'", [$like])))
                    ->orderByDesc('fecha_emision')
                    ->orderByDesc('id')
                    ->limit(12)
                    ->get(['id', 'tramite_id', 'numero_documento', 'tipo_documento'])
                    ->map(fn (TramiteDocumentoFinal $documento): array => [
                        'id' => $documento->id,
                        'tramite_id' => $documento->tramite_id,
                        'numero' => $documento->numero_documento,
                        'tipo_documento' => $documento->tipo_documento,
                    ])->all();
            }
        }

        return Inertia::render('buscar', [
            'query' => $query,
            'status' => $status,
            'results' => $results,
            'reviewer' => $request->user()->rol === 'docente',
            'administrator' => $request->user()->rol === 'administrador',
        ]);
    }
}
