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
use App\Services\Tramites\GenerateTramiteFinalDocument;
use App\Services\Tramites\PrepareTramiteForAssignment;
use App\Services\Tramites\SaveTramiteDraft;
use App\Services\Tramites\TramiteTemplateEligibility;
use App\Services\Tramites\TramiteTypeCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class TramiteBorradorController extends Controller
{
    public function show(Tramite $tramite, TramiteBorrador $borrador): InertiaResponse
    {
        abort_unless($borrador->tramite_id === $tramite->id, 404);

        return Inertia::render('tramites/borrador-preview', [
            'tramite' => ['id' => $tramite->id, 'codigo' => $tramite->codigo],
            'borrador' => [
                'id' => $borrador->id,
                'version' => $borrador->version,
                'estado' => $borrador->estado,
                'actual' => $borrador->es_actual,
                'plantilla' => $borrador->plantilla()->value('nombre'),
                'version_plantilla' => $borrador->version_plantilla,
                'creador' => $borrador->creador()->value('name'),
                'created_at' => $borrador->created_at?->toIso8601String(),
                'contenido' => $borrador->contenido_renderizado,
                'puede_pdf' => $borrador->contenido_plantilla_snapshot !== null
                    && $borrador->contenido_renderizado !== null,
            ],
        ]);
    }

    public function pdf(Request $request, Tramite $tramite, TramiteBorrador $borrador, GenerateTramiteFinalDocument $generate): Response
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        abort_unless($borrador->contenido_plantilla_snapshot !== null && $borrador->contenido_renderizado !== null, 409);

        $pdf = $generate->preview($tramite, $borrador, $actor);
        $response = response($pdf['bytes'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="borrador-'.$tramite->codigo.'-v'.$borrador->version.'.pdf"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }

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
            'modelo_oficial_pendiente' => in_array($tramite->tipo_documento, config('tramites.modelos_oficiales_pendientes', []), true),
            'plantillas' => TramitePlantilla::query()
                ->where('activa', true)
                ->whereIn('tipo_documento_salida', DB::table('tipos_documento_salida')->where('activo', true)->select('codigo'))
                ->when(! TramiteTypeCatalog::requiresApplicant($tramite->tipo_documento), function (Builder $query) use ($tramite): void {
                    $query->where('tipo_documento_salida', $tramite->formato_salida)
                        ->where('modalidad', $tramite->modalidad_documento);
                })
                ->orderBy('nombre')
                ->get(['id', 'codigo', 'nombre', 'descripcion', 'tipo_documento_salida', 'modalidad', 'estado', 'activa'])
                ->filter(fn (TramitePlantilla $plantilla): bool => TramiteTemplateEligibility::availableForDraft($tramite, $plantilla))
                ->values()
                ->map(fn (TramitePlantilla $plantilla): array => [
                    'id' => $plantilla->id,
                    'codigo' => $plantilla->codigo,
                    'nombre' => $plantilla->nombre,
                    'descripcion' => $plantilla->descripcion,
                    'modalidad' => $plantilla->modalidad,
                    'campos' => $this->camposEditables($plantilla),
                ])->all(),
            'usuarios' => $this->usuariosAutorizados(),
            'destinatarios_sugeridos' => $this->destinatariosSugeridos(),
            'borrador' => $borrador === null ? null : [
                'id' => $borrador->id,
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
                'campos' => $this->valoresCampos($borrador),
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
            'campos' => $this->camposEditables($plantilla),
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
            'usuarios' => $this->usuariosAutorizados(),
            'destinatarios_sugeridos' => $this->destinatariosSugeridos(),
            'borrador' => [
                'id' => $borrador->id,
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
                'campos' => $this->valoresCampos($borrador),
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

    /**
     * @return array<int, array{clave: string, etiqueta: string, tipo: string, obligatorio: bool, maximo: int, predeterminado: string, ayuda: ?string}>
     */
    private function camposEditables(TramitePlantilla $plantilla): array
    {
        return DB::table('tramite_plantilla_campos')->where('plantilla_id', $plantilla->id)
            ->where('activo', true)->whereNull('fuente_automatica')
            ->whereNotIn('clave_variable', ['ASUNTO', 'LUGAR', 'FECHA', 'INTRODUCCION', 'CONTENIDO_PRINCIPAL', 'CIERRE'])
            ->orderBy('orden')->get()
            ->map(fn ($campo): array => [
                'clave' => $campo->clave_variable,
                'etiqueta' => $campo->etiqueta,
                'tipo' => $campo->tipo_campo,
                'obligatorio' => (bool) $campo->obligatorio,
                'maximo' => (int) ($campo->longitud_maxima ?? 60000),
                'predeterminado' => (string) ($campo->valor_predeterminado ?? ''),
                'ayuda' => $campo->texto_ayuda,
            ])->all();
    }

    /** @return array<string, string> */
    private function valoresCampos(TramiteBorrador $borrador): array
    {
        return DB::table('tramite_borrador_valores as valor')
            ->join('tramite_plantilla_campos as campo', 'campo.id', '=', 'valor.campo_id')
            ->where('valor.borrador_id', $borrador->id)
            ->whereNull('campo.fuente_automatica')
            ->whereNotIn('campo.clave_variable', ['ASUNTO', 'LUGAR', 'FECHA', 'INTRODUCCION', 'CONTENIDO_PRINCIPAL', 'CIERRE'])
            ->pluck('valor.valor', 'campo.clave_variable')->all();
    }

    /** @return array<int, array{id: int, name: string, rol: string, cargo: string, firma_registrada: bool}> */
    private function usuariosAutorizados(): array
    {
        return DB::table('users as usuario')
            ->join('cargos_institucionales as cargo', 'cargo.id', '=', 'usuario.cargo_institucional_id')
            ->where('usuario.activo', true)
            ->where('usuario.estado_cuenta', 'activo')
            ->whereIn('usuario.rol', ['docente', 'administrador'])
            ->orderBy('usuario.name')
            ->get(['usuario.id', 'usuario.name', 'usuario.rol', 'cargo.nombre as cargo'])
            ->map(fn ($usuario): array => [
                'id' => (int) $usuario->id,
                'name' => $usuario->name,
                'rol' => $usuario->rol,
                'cargo' => $usuario->cargo,
                'firma_registrada' => Storage::disk('local')->exists('firmas-perfil/'.$usuario->id.'.jpg'),
            ])->all();
    }

    /** @return array<int, array{key: string, label: string, nombres: string, apellidos: string, cargo: string, correo: string}> */
    private function destinatariosSugeridos(): array
    {
        $institucionales = DB::table('destinatarios_institucionales')
            ->where('activo', true)
            ->orderBy('apellidos')->orderBy('nombres')
            ->get(['id', 'nombres', 'apellidos', 'cargo', 'correo'])
            ->map(fn ($destinatario): array => [
                'key' => 'institucional-'.$destinatario->id,
                'label' => trim($destinatario->nombres.' '.$destinatario->apellidos).' · '.$destinatario->cargo,
                'nombres' => $destinatario->nombres,
                'apellidos' => $destinatario->apellidos,
                'cargo' => $destinatario->cargo,
                'correo' => (string) $destinatario->correo,
            ])->all();

        $docentes = DB::table('users as usuario')
            ->leftJoin('cargos_institucionales as cargo', 'cargo.id', '=', 'usuario.cargo_institucional_id')
            ->where('usuario.rol', 'docente')
            ->where('usuario.activo', true)
            ->where('usuario.estado_cuenta', 'activo')
            ->orderBy('usuario.name')
            ->get(['usuario.id', 'usuario.name', 'usuario.nombres', 'usuario.apellidos', 'usuario.email', 'cargo.nombre as cargo'])
            ->map(fn ($usuario): array => [
                'key' => 'docente-'.$usuario->id,
                'label' => $usuario->name.' · '.($usuario->cargo ?: 'Docente'),
                'nombres' => trim((string) ($usuario->nombres ?: $usuario->name)),
                'apellidos' => trim((string) $usuario->apellidos),
                'cargo' => (string) ($usuario->cargo ?: 'Docente'),
                'correo' => (string) $usuario->email,
            ])->all();

        return [...$institucionales, ...$docentes];
    }
}
