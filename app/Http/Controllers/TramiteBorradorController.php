<?php

namespace App\Http\Controllers;

use App\Http\Requests\CorrectTramiteDraftRequest;
use App\Http\Requests\StoreTramiteBorradorRequest;
use App\Models\Tramite;
use App\Models\TramiteAsignacion;
use App\Models\TramiteBorrador;
use App\Models\TramitePlantilla;
use App\Models\TramiteRondaRevision;
use App\Models\User;
use App\Services\Tramites\PrepareTramiteForAssignment;
use App\Services\Tramites\SaveTramiteDraft;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class TramiteBorradorController extends Controller
{
    public function create(Tramite $tramite): InertiaResponse
    {
        abort_unless(in_array($tramite->estado, ['digitalizado', 'borrador_preparado'], true), 409);

        $borrador = $tramite->borradorActual;

        return Inertia::render('tramites/borrador', [
            'modo' => 'borrador',
            'tramite' => [
                'id' => $tramite->id,
                'codigo' => $tramite->codigo,
                'asunto' => $tramite->asunto,
                'fecha_recepcion' => $tramite->fecha_recepcion->toDateString(),
            ],
            'hoy' => now()->toDateString(),
            'plantillas' => TramitePlantilla::query()
                ->where('estado', 'publicada')
                ->where('activa', true)
                ->orderBy('nombre')
                ->get(['id', 'codigo', 'nombre', 'descripcion', 'modalidad', 'requiere_firma_fisica', 'permite_no_firma'])
                ->map(fn (TramitePlantilla $plantilla): array => [
                    'id' => $plantilla->id,
                    'codigo' => $plantilla->codigo,
                    'nombre' => $plantilla->nombre,
                    'descripcion' => $plantilla->descripcion,
                    'modalidad' => $plantilla->modalidad,
                    'requiere_firma_fisica' => $plantilla->requiere_firma_fisica,
                    'permite_no_firma' => $plantilla->permite_no_firma,
                ])->all(),
            'usuarios' => User::query()
                ->where('activo', true)
                ->whereIn('rol', ['docente', 'administrador'])
                ->orderBy('name')
                ->get(['id', 'name', 'rol'])
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'rol' => $user->rol,
                ])->all(),
            'borrador' => $borrador === null ? null : [
                'plantilla_id' => $borrador->plantilla_id,
                'remitente_id' => $borrador->remitente_id,
                'firmante_id' => $borrador->firmante_id,
                'fecha_documento' => $borrador->fecha_documento->toDateString(),
                'lugar' => $borrador->lugar,
                'asunto' => $borrador->asunto,
                'introduccion' => $borrador->introduccion,
                'contenido_principal' => $borrador->contenido_principal,
                'cierre' => $borrador->cierre,
                'destinatarios' => $borrador->destinatarios,
                'personas_mencionadas' => $borrador->personas_mencionadas,
                'adjuntos' => collect($borrador->adjuntos)->pluck('id')->all(),
                'version' => $borrador->version,
                'estado' => $borrador->estado,
            ],
            'archivos' => $tramite->documentos()
                ->where('vigente', true)
                ->orderBy('id')
                ->get(['id', 'nombre_original', 'categoria', 'version'])
                ->map(fn ($documento): array => [
                    'id' => $documento->id,
                    'nombre' => $documento->nombre_original,
                    'categoria' => $documento->categoria,
                    'version' => $documento->version,
                ])->all(),
            'versiones' => TramiteBorrador::query()
                ->with('plantilla:id,nombre')
                ->where('tramite_id', $tramite->id)
                ->orderByDesc('version')
                ->get(['id', 'tramite_id', 'plantilla_id', 'version', 'estado', 'es_actual', 'created_at'])
                ->map(fn (TramiteBorrador $version): array => [
                    'id' => $version->id,
                    'version' => $version->version,
                    'estado' => $version->estado,
                    'actual' => $version->es_actual,
                    'plantilla' => $version->plantilla->nombre,
                    'created_at' => $version->created_at?->toIso8601String(),
                ])->all(),
        ]);
    }

    public function correction(Tramite $tramite): InertiaResponse
    {
        abort_unless($tramite->estado === 'observado', 409);

        $asignacion = TramiteAsignacion::query()
            ->where('tramite_id', $tramite->id)
            ->where('activa', true)
            ->first();
        abort_unless($asignacion !== null, 409);

        $ronda = TramiteRondaRevision::query()
            ->with('observaciones.respuesta')
            ->where('tramite_id', $tramite->id)
            ->where('asignacion_id', $asignacion->id)
            ->where('estado', 'observada')
            ->orderByDesc('numero_ronda')
            ->first();
        abort_unless($ronda !== null, 409);

        $borrador = $tramite->borradorActual()->with('plantilla')->first();
        abort_unless($borrador !== null && $borrador->estado === 'preparado_asignacion', 409);

        $plantilla = $borrador->plantilla;
        $plantillas = [[
            'id' => $plantilla->id,
            'codigo' => $plantilla->codigo,
            'nombre' => $plantilla->nombre,
            'descripcion' => $plantilla->descripcion,
            'modalidad' => $plantilla->modalidad,
            'requiere_firma_fisica' => $plantilla->requiere_firma_fisica,
            'permite_no_firma' => $plantilla->permite_no_firma,
        ]];

        return Inertia::render('tramites/borrador', [
            'modo' => 'corregir',
            'tramite' => [
                'id' => $tramite->id,
                'codigo' => $tramite->codigo,
                'asunto' => $tramite->asunto,
                'fecha_recepcion' => $tramite->fecha_recepcion->toDateString(),
            ],
            'hoy' => now()->toDateString(),
            'plantillas' => $plantillas,
            'usuarios' => User::query()
                ->where('activo', true)
                ->whereIn('rol', ['docente', 'administrador'])
                ->orderBy('name')
                ->get(['id', 'name', 'rol'])
                ->map(fn (User $user): array => ['id' => $user->id, 'name' => $user->name, 'rol' => $user->rol])
                ->all(),
            'borrador' => [
                'plantilla_id' => $borrador->plantilla_id,
                'remitente_id' => $borrador->remitente_id,
                'firmante_id' => $borrador->firmante_id,
                'fecha_documento' => $borrador->fecha_documento->toDateString(),
                'lugar' => $borrador->lugar,
                'asunto' => $borrador->asunto,
                'introduccion' => $borrador->introduccion,
                'contenido_principal' => $borrador->contenido_principal,
                'cierre' => $borrador->cierre,
                'destinatarios' => $borrador->destinatarios,
                'personas_mencionadas' => $borrador->personas_mencionadas,
                'adjuntos' => collect($borrador->adjuntos)->pluck('id')->all(),
                'version' => $borrador->version,
                'estado' => $borrador->estado,
            ],
            'archivos' => $tramite->documentos()
                ->where('vigente', true)
                ->orderBy('id')
                ->get(['id', 'nombre_original', 'categoria', 'version'])
                ->map(fn ($documento): array => [
                    'id' => $documento->id,
                    'nombre' => $documento->nombre_original,
                    'categoria' => $documento->categoria,
                    'version' => $documento->version,
                ])->all(),
            'versiones' => TramiteBorrador::query()
                ->with('plantilla:id,nombre')
                ->where('tramite_id', $tramite->id)
                ->orderByDesc('version')
                ->get(['id', 'tramite_id', 'plantilla_id', 'version', 'estado', 'es_actual', 'created_at'])
                ->map(fn (TramiteBorrador $version): array => [
                    'id' => $version->id,
                    'version' => $version->version,
                    'estado' => $version->estado,
                    'actual' => $version->es_actual,
                    'plantilla' => $version->plantilla->nombre,
                    'created_at' => $version->created_at?->toIso8601String(),
                ])->all(),
            'resumen_observacion' => $ronda->resumen_observacion,
            'observaciones' => $ronda->observaciones->map(fn ($observacion): array => [
                'id' => $observacion->id,
                'categoria' => $observacion->categoria,
                'titulo' => $observacion->titulo,
                'descripcion' => $observacion->descripcion,
                'seccion' => $observacion->seccion,
                'obligatoria' => $observacion->obligatoria,
                'respuesta' => $observacion->respuesta?->respuesta,
            ])->all(),
        ]);
    }

    public function store(StoreTramiteBorradorRequest $request, Tramite $tramite, SaveTramiteDraft $saveTramiteDraft): RedirectResponse
    {
        $borrador = $saveTramiteDraft->execute($tramite, $request->validated(), $request->user());

        return redirect()->route('tramites.show', $tramite)
            ->with('success', 'Se guardó la versión '.$borrador->version.' del borrador.');
    }

    public function correct(CorrectTramiteDraftRequest $request, Tramite $tramite, SaveTramiteDraft $saveTramiteDraft): RedirectResponse
    {
        $borrador = $saveTramiteDraft->correct($tramite, $request->validated(), $request->user());

        return redirect()->route('tramites.show', $tramite)
            ->with('success', 'La corrección quedó guardada en la versión '.$borrador->version.' y enviada al mismo revisor.');
    }

    public function prepareAssignment(Request $request, Tramite $tramite, PrepareTramiteForAssignment $prepareTramiteForAssignment): RedirectResponse
    {
        $prepareTramiteForAssignment->execute($tramite->id, $request->user()->id);

        return redirect()->route('tramites.show', $tramite)
            ->with('success', 'El trámite quedó pendiente de asignación.');
    }
}
