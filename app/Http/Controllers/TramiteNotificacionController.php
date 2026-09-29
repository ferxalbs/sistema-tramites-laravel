<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class TramiteNotificacionController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $filters = $request->validate([
            'estado' => ['sometimes', Rule::in(['todas', 'no_leidas', 'leidas'])],
            'prioridad' => ['sometimes', Rule::in(['todas', 'baja', 'normal', 'alta', 'urgente'])],
        ]);
        $status = $filters['estado'] ?? 'todas';
        $priority = $filters['prioridad'] ?? 'todas';
        $user = $request->user();

        $notifications = DB::table('tramite_notificaciones')
            ->where('usuario_id', $user->id)
            ->when($status !== 'todas', fn ($query) => $query->where('leida', $status === 'leidas'))
            ->when($priority !== 'todas', fn ($query) => $query->where('prioridad', $priority))
            ->orderBy('leida')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString()
            ->through(function ($notification) use ($user): array {
                $url = $notification->tramite_id === null ? null : match ($user->rol) {
                    'estudiante' => route('estudiante.tramites.show', $notification->tramite_id, false),
                    'docente' => DB::table('tramite_asignaciones')
                        ->where('tramite_id', $notification->tramite_id)
                        ->where('revisor_id', $user->id)
                        ->where('activa', true)
                        ->exists() ? route('asignaciones.docente.show', $notification->tramite_id, false) : null,
                    default => route('tramites.show', $notification->tramite_id, false),
                };

                return [
                    'id' => $notification->id,
                    'titulo' => $notification->titulo,
                    'mensaje' => $notification->mensaje,
                    'prioridad' => $notification->prioridad,
                    'leida' => (bool) $notification->leida,
                    'fecha' => $notification->created_at,
                    'url' => $url,
                ];
            });

        return Inertia::render('notificaciones', [
            'notifications' => $notifications,
            'filters' => ['estado' => $status, 'prioridad' => $priority],
        ]);
    }

    public function read(Request $request, int $notification): RedirectResponse
    {
        DB::transaction(function () use ($request, $notification): void {
            $updated = DB::table('tramite_notificaciones')
                ->where('id', $notification)
                ->where('usuario_id', $request->user()->id)
                ->where('leida', false)
                ->update(['leida' => true, 'fecha_lectura' => now(), 'updated_at' => now()]);

            if ($updated === 0) {
                abort_unless(DB::table('tramite_notificaciones')
                    ->where('id', $notification)
                    ->where('usuario_id', $request->user()->id)
                    ->exists(), 404);

                return;
            }

            DB::table('tramite_notificacion_eventos')->insert([
                'notificacion_id' => $notification,
                'actor_id' => $request->user()->id,
                'accion' => 'marcar_leida',
                'created_at' => now(),
            ]);
        });

        return to_route('notificaciones.index');
    }

    public function readAll(Request $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $updated = DB::table('tramite_notificaciones')
                ->where('usuario_id', $request->user()->id)
                ->where('leida', false)
                ->update(['leida' => true, 'fecha_lectura' => now(), 'updated_at' => now()]);

            if ($updated > 0) {
                DB::table('tramite_notificacion_eventos')->insert([
                    'actor_id' => $request->user()->id,
                    'accion' => 'marcar_todas',
                    'created_at' => now(),
                ]);
            }
        });

        return to_route('notificaciones.index');
    }
}
