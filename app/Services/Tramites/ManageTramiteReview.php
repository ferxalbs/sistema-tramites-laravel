<?php

namespace App\Services\Tramites;

use App\Models\Tramite;
use App\Models\TramiteAsignacion;
use App\Models\TramiteBorrador;
use App\Models\TramiteEvento;
use App\Models\TramiteObservacionRevision;
use App\Models\TramiteRondaRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManageTramiteReview
{
    public function start(Tramite $tramite, User $actor): TramiteRondaRevision
    {
        return DB::transaction(function () use ($tramite, $actor): TramiteRondaRevision {
            $asignacion = $this->ownedAssignment($tramite, $actor);
            $estado = DB::table('tramites')->where('id', $tramite->id)->value('estado');

            abort_unless(in_array($estado, ['asignado', 'corregido'], true), 409);

            $borrador = TramiteBorrador::query()
                ->where('tramite_id', $tramite->id)
                ->where('es_actual', true)
                ->where('estado', 'preparado_asignacion')
                ->first();

            abort_unless($borrador !== null, 409);
            abort_if(TramiteRondaRevision::query()->where('tramite_id', $tramite->id)->where('activa', true)->exists(), 409);

            $actualizados = DB::table('tramites')
                ->where('id', $tramite->id)
                ->where('estado', $estado)
                ->update(['estado' => 'en_revision', 'updated_at' => now()]);

            abort_unless($actualizados === 1, 409);

            $numeroRonda = (int) (TramiteRondaRevision::query()->where('tramite_id', $tramite->id)->max('numero_ronda') ?? 0) + 1;
            $ronda = TramiteRondaRevision::query()->create([
                'tramite_id' => $tramite->id,
                'asignacion_id' => $asignacion->id,
                'numero_ronda' => $numeroRonda,
                'revisor_id' => $actor->id,
                'borrador_id' => $borrador->id,
                'estado' => 'en_revision',
                'activa' => true,
                'iniciada_en' => now(),
            ]);

            TramiteAsignacion::query()
                ->whereKey($asignacion->id)
                ->where('activa', true)
                ->update(['estado' => 'en_revision', 'updated_at' => now()]);

            if ($asignacion->fecha_inicio_revision === null) {
                TramiteAsignacion::query()
                    ->whereKey($asignacion->id)
                    ->whereNull('fecha_inicio_revision')
                    ->update(['fecha_inicio_revision' => now(), 'updated_at' => now()]);
            }

            $this->registrarEvento(
                $tramite->id,
                $actor->id,
                'inicio_revision',
                'Se inició la ronda de revisión '.$numeroRonda.'.',
                $estado,
                'en_revision',
                ['ronda_id' => $ronda->id, 'version' => $borrador->version],
            );

            return $ronda;
        });
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function observe(Tramite $tramite, array $datos, User $actor): void
    {
        $observaciones = $this->normalizarObservaciones($datos['observaciones'] ?? []);
        $resumen = trim((string) $datos['resumen']);

        if (mb_strlen($resumen) < 8) {
            throw ValidationException::withMessages(['resumen' => 'El resumen de la observación debe tener al menos 8 caracteres.']);
        }

        DB::transaction(function () use ($tramite, $actor, $observaciones, $resumen): void {
            $asignacion = $this->ownedAssignment($tramite, $actor);
            abort_unless(DB::table('tramites')->where('id', $tramite->id)->where('estado', 'en_revision')->exists(), 409);

            $ronda = TramiteRondaRevision::query()
                ->where('tramite_id', $tramite->id)
                ->where('asignacion_id', $asignacion->id)
                ->where('revisor_id', $actor->id)
                ->where('estado', 'en_revision')
                ->where('activa', true)
                ->first();

            abort_unless($ronda !== null, 409);

            foreach ($observaciones as $index => $observacion) {
                $registro = $ronda->observaciones()->create([
                    'tramite_id' => $tramite->id,
                    'revisor_id' => $actor->id,
                    'categoria' => $observacion['categoria'],
                    'titulo' => $observacion['titulo'],
                    'descripcion' => $observacion['descripcion'],
                    'seccion' => $observacion['seccion'],
                    'obligatoria' => $observacion['obligatoria'],
                    'visible_para_interesado' => $observacion['visible_para_interesado'],
                    'orden' => $index + 1,
                ]);

                $this->registrarEvento(
                    $tramite->id,
                    $actor->id,
                    'punto_observado',
                    'Se registró un punto de observación.',
                    null,
                    null,
                    [
                        'ronda_id' => $ronda->id,
                        'observacion_id' => $registro->id,
                        'categoria' => $registro->categoria,
                        'obligatoria' => $registro->obligatoria,
                        'visible_para_interesado' => $registro->visible_para_interesado,
                    ],
                );
            }

            $cerrada = TramiteRondaRevision::query()
                ->whereKey($ronda->id)
                ->where('estado', 'en_revision')
                ->where('activa', true)
                ->update([
                    'estado' => 'observada',
                    'activa' => false,
                    'resumen_observacion' => $resumen,
                    'cerrada_en' => now(),
                    'updated_at' => now(),
                ]);

            abort_unless($cerrada === 1, 409);

            $actualizados = DB::table('tramites')
                ->where('id', $tramite->id)
                ->where('estado', 'en_revision')
                ->update(['estado' => 'observado', 'updated_at' => now()]);

            abort_unless($actualizados === 1, 409);

            TramiteAsignacion::query()->whereKey($asignacion->id)->where('activa', true)->update([
                'estado' => 'activa',
                'updated_at' => now(),
            ]);

            $this->registrarEvento(
                $tramite->id,
                $actor->id,
                'observacion',
                'El trámite quedó observado para su corrección.',
                'en_revision',
                'observado',
                ['ronda_id' => $ronda->id, 'cantidad' => count($observaciones)],
            );
        });
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function decide(Tramite $tramite, array $datos, User $actor): void
    {
        $decision = (string) $datos['decision'];
        $conclusion = trim((string) $datos['conclusion']);
        $comentarioPublico = trim((string) ($datos['comentario_publico'] ?? ''));
        $comentarioInterno = trim((string) ($datos['comentario_interno'] ?? ''));

        if (! in_array($decision, ['aprobar', 'rechazar'], true)) {
            throw ValidationException::withMessages(['decision' => 'Seleccione una decisión válida.']);
        }

        if (mb_strlen($conclusion) < 8) {
            throw ValidationException::withMessages(['conclusion' => 'La conclusión o el fundamento debe tener al menos 8 caracteres.']);
        }

        if ($decision === 'rechazar' && mb_strlen($comentarioPublico) < 8) {
            throw ValidationException::withMessages(['comentario_publico' => 'El rechazo requiere un comentario público comprensible.']);
        }

        DB::transaction(function () use ($tramite, $actor, $decision, $conclusion, $comentarioPublico, $comentarioInterno): void {
            $asignacion = $this->ownedAssignment($tramite, $actor);
            abort_unless(DB::table('tramites')->where('id', $tramite->id)->where('estado', 'en_revision')->exists(), 409);

            $ronda = TramiteRondaRevision::query()
                ->where('tramite_id', $tramite->id)
                ->where('asignacion_id', $asignacion->id)
                ->where('revisor_id', $actor->id)
                ->where('estado', 'en_revision')
                ->where('activa', true)
                ->first();

            abort_unless($ronda !== null, 409);

            if ($decision === 'aprobar' && $this->hayObservacionesObligatoriasPendientes($asignacion->id)) {
                throw ValidationException::withMessages(['decision' => 'No se puede aprobar mientras existan observaciones obligatorias sin respuesta.']);
            }

            $estadoNuevo = $decision === 'aprobar' ? 'aprobado' : 'rechazado';
            $cerrada = TramiteRondaRevision::query()
                ->whereKey($ronda->id)
                ->where('estado', 'en_revision')
                ->where('activa', true)
                ->update([
                    'estado' => $estadoNuevo,
                    'activa' => false,
                    'conclusion' => $conclusion,
                    'comentario_publico' => $comentarioPublico === '' ? null : $comentarioPublico,
                    'comentario_interno' => $comentarioInterno === '' ? null : $comentarioInterno,
                    'cerrada_en' => now(),
                    'updated_at' => now(),
                ]);

            abort_unless($cerrada === 1, 409);

            $actualizados = DB::table('tramites')
                ->where('id', $tramite->id)
                ->where('estado', 'en_revision')
                ->update(['estado' => $estadoNuevo, 'updated_at' => now()]);

            abort_unless($actualizados === 1, 409);

            $actualizados = TramiteAsignacion::query()
                ->whereKey($asignacion->id)
                ->where('activa', true)
                ->update([
                    'activa' => false,
                    'estado' => $estadoNuevo,
                    'fecha_finalizacion' => now(),
                    'motivo_finalizacion' => $conclusion,
                    'updated_at' => now(),
                ]);

            abort_unless($actualizados === 1, 409);

            $this->registrarEvento(
                $tramite->id,
                $actor->id,
                $decision === 'aprobar' ? 'revision_aprobada' : 'revision_rechazada',
                $decision === 'aprobar' ? 'El trámite fue aprobado.' : 'El trámite fue rechazado.',
                'en_revision',
                $estadoNuevo,
                ['ronda_id' => $ronda->id, 'asignacion_id' => $asignacion->id],
            );
        });
    }

    private function ownedAssignment(Tramite $tramite, User $actor): TramiteAsignacion
    {
        abort_unless($actor->activo && in_array($actor->rol, ['docente', 'administrador'], true), 403);

        $destino = $actor->rol === 'docente' ? 'docente' : 'oficina';
        $asignacion = TramiteAsignacion::query()
            ->where('tramite_id', $tramite->id)
            ->where('revisor_id', $actor->id)
            ->where('rol_revisor', $actor->rol)
            ->where('destino', $destino)
            ->where('activa', true)
            ->first();

        abort_unless($asignacion !== null, 403);

        return $asignacion;
    }

    /**
     * @return array<int, array{categoria: string, titulo: string, descripcion: string, seccion: ?string, obligatoria: bool, visible_para_interesado: bool}>
     */
    private function normalizarObservaciones(mixed $items): array
    {
        if (! is_array($items) || $items === [] || count($items) > 20) {
            throw ValidationException::withMessages(['observaciones' => 'Agregue entre una y veinte observaciones.']);
        }

        $observaciones = [];

        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                throw ValidationException::withMessages(["observaciones.$index" => 'La observación no tiene un formato válido.']);
            }

            $categoria = trim(strip_tags((string) ($item['categoria'] ?? '')));
            $titulo = trim(strip_tags((string) ($item['titulo'] ?? '')));
            $descripcion = trim(strip_tags((string) ($item['descripcion'] ?? '')));
            $seccion = trim(strip_tags((string) ($item['seccion'] ?? '')));

            if (! in_array($categoria, TramiteObservacionRevision::CATEGORIAS, true)) {
                throw ValidationException::withMessages(["observaciones.$index.categoria" => 'Seleccione una categoría válida.']);
            }

            if (mb_strlen($titulo) < 3 || mb_strlen($descripcion) < 5) {
                throw ValidationException::withMessages(["observaciones.$index.descripcion" => 'Cada observación requiere un título y una descripción de al menos 5 caracteres.']);
            }

            $observaciones[] = [
                'categoria' => $categoria,
                'titulo' => $titulo,
                'descripcion' => $descripcion,
                'seccion' => $seccion === '' ? null : $seccion,
                'obligatoria' => (bool) ($item['obligatoria'] ?? false),
                'visible_para_interesado' => (bool) ($item['visible_para_interesado'] ?? false),
            ];
        }

        return $observaciones;
    }

    private function hayObservacionesObligatoriasPendientes(int $asignacionId): bool
    {
        return DB::table('tramite_observaciones_revision as observaciones')
            ->join('tramite_rondas_revision as rondas', 'rondas.id', '=', 'observaciones.ronda_id')
            ->leftJoin('tramite_respuestas_observacion as respuestas', 'respuestas.observacion_id', '=', 'observaciones.id')
            ->where('rondas.asignacion_id', $asignacionId)
            ->where('observaciones.obligatoria', true)
            ->whereNull('respuestas.id')
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $metadatos
     */
    private function registrarEvento(int $tramiteId, int $actorId, string $accion, string $descripcion, ?string $estadoAnterior, ?string $estadoNuevo, array $metadatos): void
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
}
