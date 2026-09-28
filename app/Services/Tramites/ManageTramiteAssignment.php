<?php

namespace App\Services\Tramites;

use App\Models\Tramite;
use App\Models\TramiteAsignacion;
use App\Models\TramiteEvento;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManageTramiteAssignment
{
    /**
     * @return array{docente: array<int, array{id: int, name: string, rol: string, carga_activa: int}>, oficina: array<int, array{id: int, name: string, rol: string, carga_activa: int}>}
     */
    public function eligibleReviewers(): array
    {
        $users = User::query()
            ->where('activo', true)
            ->whereIn('rol', ['docente', 'administrador'])
            ->select(['id', 'name', 'rol'])
            ->selectSub(
                DB::table('tramite_asignaciones')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('revisor_id', 'users.id')
                    ->where('activa', true),
                'carga_activa',
            )
            ->orderBy('carga_activa')
            ->orderBy('name')
            ->get();

        return [
            'docente' => $users->where('rol', 'docente')->map(fn (User $user): array => $this->reviewerOption($user))->values()->all(),
            'oficina' => $users->where('rol', 'administrador')->map(fn (User $user): array => $this->reviewerOption($user))->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function assign(Tramite $tramite, array $datos, User $actor): TramiteAsignacion
    {
        $this->authorizeActor($actor);

        return DB::transaction(function () use ($tramite, $datos, $actor): TramiteAsignacion {
            abort_unless(DB::table('tramites')->where('id', $tramite->id)->value('estado') === 'pendiente_asignacion', 409);
            $this->assertPreparedDraft($tramite->id);

            if (TramiteAsignacion::query()->where('tramite_id', $tramite->id)->where('activa', true)->exists()) {
                abort(409);
            }

            $revisor = $this->reviewer((int) $datos['revisor_id'], (string) $datos['destino'], $actor);
            $actualizados = DB::table('tramites')
                ->where('id', $tramite->id)
                ->where('estado', 'pendiente_asignacion')
                ->update(['estado' => 'asignado', 'updated_at' => now()]);

            abort_unless($actualizados === 1, 409);

            $asignacion = TramiteAsignacion::query()->create([
                'tramite_id' => $tramite->id,
                'destino' => $datos['destino'],
                'revisor_id' => $revisor->id,
                'rol_revisor' => $revisor->rol,
                'asignado_por' => $actor->id,
                'motivo' => trim($datos['motivo']),
                'instrucciones_revision' => $this->textoOpcional($datos['instrucciones_revision'] ?? null),
                'fecha_esperada' => $datos['fecha_esperada'] ?? null,
                'estado' => 'activa',
                'activa' => true,
            ]);

            $this->registrarEvento(
                $tramite->id,
                $actor->id,
                'asignacion_creada',
                'El trámite fue asignado a '.$revisor->name.'.',
                'pendiente_asignacion',
                'asignado',
                [
                    'asignacion_id' => $asignacion->id,
                    'destino' => $asignacion->destino,
                    'revisor_id' => $revisor->id,
                ],
            );

            return $asignacion;
        });
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function reassign(Tramite $tramite, array $datos, User $actor): TramiteAsignacion
    {
        $this->authorizeActor($actor);

        return DB::transaction(function () use ($tramite, $datos, $actor): TramiteAsignacion {
            abort_unless(DB::table('tramites')->where('id', $tramite->id)->value('estado') === 'asignado', 409);

            $actual = TramiteAsignacion::query()
                ->where('tramite_id', $tramite->id)
                ->where('activa', true)
                ->first();

            abort_unless($actual !== null && $actual->fecha_inicio_revision === null, 409);

            $revisor = $this->reviewer((int) $datos['revisor_id'], (string) $datos['destino'], $actor);
            $ahora = now();
            $actualizados = TramiteAsignacion::query()
                ->whereKey($actual->id)
                ->where('activa', true)
                ->whereNull('fecha_inicio_revision')
                ->update([
                    'activa' => false,
                    'estado' => 'reasignada',
                    'fecha_finalizacion' => $ahora,
                    'motivo_finalizacion' => trim($datos['motivo_reasignacion']),
                    'updated_at' => $ahora,
                ]);

            abort_unless($actualizados === 1, 409);

            $nueva = TramiteAsignacion::query()->create([
                'tramite_id' => $tramite->id,
                'destino' => $datos['destino'],
                'revisor_id' => $revisor->id,
                'rol_revisor' => $revisor->rol,
                'asignado_por' => $actor->id,
                'motivo' => trim((string) ($datos['motivo'] ?? '')) ?: trim($datos['motivo_reasignacion']),
                'instrucciones_revision' => $this->textoOpcional($datos['instrucciones_revision'] ?? null),
                'fecha_esperada' => $datos['fecha_esperada'] ?? null,
                'estado' => 'activa',
                'activa' => true,
            ]);

            $this->registrarEvento(
                $tramite->id,
                $actor->id,
                'asignacion_reasignada',
                'El trámite fue reasignado a '.$revisor->name.'.',
                'asignado',
                'asignado',
                [
                    'asignacion_anterior_id' => $actual->id,
                    'asignacion_nueva_id' => $nueva->id,
                    'justificacion' => trim($datos['motivo_reasignacion']),
                ],
            );

            return $nueva;
        });
    }

    public function cancel(Tramite $tramite, string $motivo, User $actor): void
    {
        $this->authorizeActor($actor);

        DB::transaction(function () use ($tramite, $motivo, $actor): void {
            abort_unless(DB::table('tramites')->where('id', $tramite->id)->value('estado') === 'asignado', 409);

            $asignacion = TramiteAsignacion::query()
                ->where('tramite_id', $tramite->id)
                ->where('activa', true)
                ->first();

            abort_unless($asignacion !== null && $asignacion->fecha_inicio_revision === null, 409);

            $ahora = now();
            $actualizados = TramiteAsignacion::query()
                ->whereKey($asignacion->id)
                ->where('activa', true)
                ->whereNull('fecha_inicio_revision')
                ->update([
                    'activa' => false,
                    'estado' => 'cancelada',
                    'fecha_finalizacion' => $ahora,
                    'motivo_finalizacion' => trim($motivo),
                    'updated_at' => $ahora,
                ]);

            abort_unless($actualizados === 1, 409);

            $actualizados = DB::table('tramites')
                ->where('id', $tramite->id)
                ->where('estado', 'asignado')
                ->update(['estado' => 'pendiente_asignacion', 'updated_at' => $ahora]);

            abort_unless($actualizados === 1, 409);

            $this->registrarEvento(
                $tramite->id,
                $actor->id,
                'asignacion_cancelada',
                'La asignación se canceló antes de iniciar la revisión.',
                'asignado',
                'pendiente_asignacion',
                ['asignacion_id' => $asignacion->id, 'motivo' => trim($motivo)],
            );
        });
    }

    private function authorizeActor(User $actor): void
    {
        abort_unless($actor->activo && $actor->rol === 'asistente', 403);
    }

    private function assertPreparedDraft(int $tramiteId): void
    {
        abort_unless(
            DB::table('tramite_borradores')
                ->where('tramite_id', $tramiteId)
                ->where('es_actual', true)
                ->where('estado', 'preparado_asignacion')
                ->exists(),
            409,
        );
    }

    private function reviewer(int $userId, string $destino, User $actor): User
    {
        $rolEsperado = match ($destino) {
            'docente' => 'docente',
            'oficina' => 'administrador',
            default => null,
        };

        if ($rolEsperado === null) {
            throw ValidationException::withMessages(['destino' => 'Seleccione un destino válido.']);
        }

        $revisor = User::query()->whereKey($userId)->first();

        if ($revisor === null || ! $revisor->activo) {
            throw ValidationException::withMessages(['revisor_id' => 'Seleccione un revisor activo.']);
        }

        if ($revisor->rol !== $rolEsperado) {
            throw ValidationException::withMessages(['revisor_id' => 'El rol del revisor no corresponde al destino.']);
        }

        if ($revisor->is($actor)) {
            throw ValidationException::withMessages(['revisor_id' => 'No puede asignarse el trámite a su propia cuenta.']);
        }

        return $revisor;
    }

    /**
     * @param  array<string, mixed>  $metadatos
     */
    private function registrarEvento(int $tramiteId, int $actorId, string $accion, string $descripcion, string $estadoAnterior, string $estadoNuevo, array $metadatos): void
    {
        TramiteEvento::query()->create([
            'tramite_id' => $tramiteId,
            'usuario_id' => $actorId,
            'accion' => $accion,
            'descripcion' => $descripcion,
            'estado_anterior' => $estadoAnterior,
            'estado_nuevo' => $estadoNuevo,
            'metadatos' => $metadatos,
        ]);
    }

    private function textoOpcional(mixed $valor): ?string
    {
        $texto = trim(strip_tags((string) ($valor ?? '')));

        return $texto === '' ? null : $texto;
    }

    /**
     * @return array{id: int, name: string, rol: string, carga_activa: int}
     */
    private function reviewerOption(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'rol' => $user->rol,
            'carga_activa' => (int) $user->carga_activa,
        ];
    }
}
