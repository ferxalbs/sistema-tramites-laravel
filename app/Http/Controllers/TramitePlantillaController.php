<?php

namespace App\Http\Controllers;

use App\Models\TramitePlantilla;
use App\Services\Tramites\TramiteTemplateEligibility;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class TramitePlantillaController extends Controller
{
    public function index(): InertiaResponse
    {
        return Inertia::render('plantillas', [
            'formatos' => DB::table('tipos_documento_salida')->where('activo', true)->orderBy('nombre')->get(['codigo', 'nombre']),
            'modalidades' => DB::table('modalidades_documento')->where('activo', true)->orderBy('nombre')->get(['codigo', 'nombre']),
            'plantillas' => TramitePlantilla::query()
                ->orderBy('codigo')
                ->orderByDesc('version')
                ->get([
                    'id', 'codigo', 'version', 'nombre', 'descripcion', 'tipo_documento_salida',
                    'modalidad', 'contenido', 'estado', 'activa', 'created_at',
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'codigo' => mb_strtoupper(trim((string) $request->input('codigo', ''))),
            'nombre' => trim((string) $request->input('nombre', '')),
            'descripcion' => trim((string) $request->input('descripcion', '')),
            'modalidad' => $request->input('modalidad') === 'sin_modalidad' ? null : $request->input('modalidad'),
        ]);
        $data = $request->validate([
            'codigo' => ['required', 'regex:/^[A-Z0-9_]{3,80}$/', 'unique:tramite_plantillas,codigo'],
            'nombre' => ['required', 'string', 'min:3', 'max:160'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'tipo_documento_salida' => ['required', Rule::in(['informe', 'memorando'])],
            'modalidad' => ['nullable', Rule::in(['simple', 'multiple'])],
            'contenido' => ['required', 'string', 'max:60000'],
        ]);
        $contenido = $this->sanitizeContent($data['contenido']);
        $this->assertEssentialVariables($contenido);
        $formato = $data['tipo_documento_salida'];
        $modalidad = $data['modalidad'] ?? null;

        if (($formato === 'informe' && $modalidad !== null) || ($formato === 'memorando' && $modalidad === null)) {
            throw ValidationException::withMessages(['modalidad' => 'La modalidad no corresponde al formato documental.']);
        }

        if (! DB::table('tipos_documento_salida')->where('codigo', $formato)->where('activo', true)->exists()
            || ($modalidad !== null && ! DB::table('modalidades_documento')->where('codigo', $modalidad)
                ->where('tipo_documento_salida', $formato)->where('activo', true)->exists())) {
            throw ValidationException::withMessages(['tipo_documento_salida' => 'Seleccione un formato y modalidad activos.']);
        }

        $source = TramitePlantilla::query()->where('tipo_documento_salida', $formato)
            ->where('estado', 'publicada')
            ->where('modalidad', $modalidad)->orderByDesc('activa')->orderByDesc('version')->first();

        if ($source === null) {
            throw ValidationException::withMessages(['tipo_documento_salida' => 'No existe una plantilla base con campos para ese formato.']);
        }

        DB::transaction(function () use ($request, $data, $contenido, $source, $formato, $modalidad): void {
            $nueva = TramitePlantilla::query()->create([
                'codigo' => $data['codigo'],
                'version' => 1,
                'nombre' => $data['nombre'],
                'descripcion' => $data['descripcion'] ?: null,
                'tipo_documento_salida' => $formato,
                'modalidad' => $modalidad,
                'contenido' => $contenido,
                'requiere_firma_fisica' => $source->requiere_firma_fisica,
                'permite_no_firma' => $source->permite_no_firma,
                'estado' => 'borrador',
                'activa' => false,
            ]);
            $this->cloneFields($source, $nueva);
            DB::table('tramite_config_events')->insert([
                'actor_id' => $request->user()->id,
                'accion' => 'creacion_plantilla',
                'entidad' => 'plantilla',
                'entidad_id' => $nueva->id,
                'valor_anterior' => 'null',
                'valor_nuevo' => json_encode(['codigo' => $nueva->codigo, 'version' => 1], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return to_route('admin.templates.index')->with('success', 'Plantilla creada como borrador.');
    }

    public function version(Request $request, TramitePlantilla $plantilla): RedirectResponse
    {
        if (TramiteTemplateEligibility::isReferentialTemplate($plantilla)) {
            throw ValidationException::withMessages(['plantilla' => 'Este modelo referencial solo sirve para pruebas de borrador hasta contar con la versión institucional aprobada.']);
        }

        $request->merge([
            'nombre' => trim((string) $request->input('nombre', '')),
            'descripcion' => trim((string) $request->input('descripcion', '')),
        ]);
        $data = $request->validate([
            'nombre' => ['required', 'string', 'min:3', 'max:160'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'contenido' => ['required', 'string', 'max:60000'],
            'publicar' => ['required', 'boolean'],
        ]);
        $contenido = $this->sanitizeContent($data['contenido']);

        $this->assertEssentialVariables($contenido);

        DB::transaction(function () use ($request, $plantilla, $data, $contenido): void {
            $version = ((int) TramitePlantilla::query()
                ->where('codigo', $plantilla->codigo)
                ->max('version')) + 1;
            $publicar = (bool) $data['publicar'];

            if ($publicar) {
                TramitePlantilla::query()->where('codigo', $plantilla->codigo)
                    ->update(['activa' => false]);
            }

            $nueva = TramitePlantilla::query()->create([
                'codigo' => $plantilla->codigo,
                'version' => $version,
                'nombre' => $data['nombre'],
                'descripcion' => $data['descripcion'] ?: null,
                'tipo_documento_salida' => $plantilla->tipo_documento_salida,
                'modalidad' => $plantilla->modalidad,
                'contenido' => $contenido,
                'requiere_firma_fisica' => $plantilla->requiere_firma_fisica,
                'permite_no_firma' => $plantilla->permite_no_firma,
                'estado' => $publicar ? 'publicada' : 'borrador',
                'activa' => $publicar,
            ]);

            $this->cloneFields($plantilla, $nueva);

            if ($publicar) {
                $plantilla->update(['estado' => 'obsoleta']);
            }

            DB::table('tramite_config_events')->insert([
                'actor_id' => $request->user()->id,
                'accion' => 'nueva_version_plantilla',
                'entidad' => 'plantilla',
                'entidad_id' => $nueva->id,
                'valor_anterior' => json_encode(['id' => $plantilla->id, 'version' => $plantilla->version], JSON_THROW_ON_ERROR),
                'valor_nuevo' => json_encode(['version' => $version, 'publicada' => $publicar], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return to_route('admin.templates.index')->with('success', 'Versión de plantilla creada.');
    }

    public function state(Request $request, TramitePlantilla $plantilla): RedirectResponse
    {
        if (TramiteTemplateEligibility::isReferentialTemplate($plantilla)) {
            throw ValidationException::withMessages(['plantilla' => 'No se puede publicar ni desactivar este modelo referencial desde el catálogo.']);
        }

        $data = $request->validate(['activa' => ['required', 'boolean']]);
        $activa = (bool) $data['activa'];

        DB::transaction(function () use ($request, $plantilla, $activa): void {
            $before = (bool) TramitePlantilla::query()->whereKey($plantilla->id)->value('activa');

            if ($before === $activa) {
                return;
            }

            if ($activa) {
                TramitePlantilla::query()->where('codigo', $plantilla->codigo)
                    ->where('id', '<>', $plantilla->id)->update(['activa' => false]);
            }

            $plantilla->update([
                'activa' => $activa,
                'estado' => $activa ? 'publicada' : 'obsoleta',
            ]);
            DB::table('tramite_config_events')->insert([
                'actor_id' => $request->user()->id,
                'accion' => $activa ? 'activacion_plantilla' : 'desactivacion_plantilla',
                'entidad' => 'plantilla',
                'entidad_id' => $plantilla->id,
                'valor_anterior' => json_encode(['activa' => $before], JSON_THROW_ON_ERROR),
                'valor_nuevo' => json_encode(['activa' => $activa], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return to_route('admin.templates.index')->with('success', 'Estado de la plantilla actualizado.');
    }

    public function fields(TramitePlantilla $plantilla): InertiaResponse
    {
        return Inertia::render('plantilla-campos', [
            'plantilla' => $plantilla->only(['id', 'codigo', 'version', 'nombre', 'estado']),
            'usada' => DB::table('tramite_borradores')->where('plantilla_id', $plantilla->id)->exists(),
            'campos' => DB::table('tramite_plantilla_campos')->where('plantilla_id', $plantilla->id)
                ->orderBy('orden')->orderBy('id')->get([
                    'id', 'clave_variable', 'etiqueta', 'grupo', 'tipo_campo', 'obligatorio',
                    'requiere_confirmacion', 'permite_html', 'orden', 'longitud_maxima',
                    'texto_ayuda', 'activo',
                ]),
        ]);
    }

    public function updateField(Request $request, TramitePlantilla $plantilla, int $field): RedirectResponse
    {
        $request->merge([
            'etiqueta' => trim((string) $request->input('etiqueta', '')),
            'grupo' => trim((string) $request->input('grupo', '')),
            'texto_ayuda' => trim((string) $request->input('texto_ayuda', '')),
        ]);
        $data = $request->validate([
            'etiqueta' => ['required', 'string', 'min:2', 'max:160'],
            'grupo' => ['required', 'string', 'max:80'],
            'tipo_campo' => ['required', Rule::in([
                'texto_corto', 'texto_largo', 'fecha', 'numero', 'select', 'usuario',
                'cargo_institucional', 'programa_estudios', 'lista_destinatarios',
                'lista_personas', 'texto_enriquecido',
            ])],
            'obligatorio' => ['required', 'boolean'],
            'requiere_confirmacion' => ['required', 'boolean'],
            'permite_html' => ['required', 'boolean'],
            'longitud_maxima' => ['nullable', 'integer', 'min:1', 'max:60000'],
            'texto_ayuda' => ['nullable', 'string', 'max:255'],
            'activo' => ['required', 'boolean'],
        ]);

        DB::transaction(function () use ($request, $plantilla, $field, $data): void {
            $before = DB::table('tramite_plantilla_campos')
                ->where('plantilla_id', $plantilla->id)->where('id', $field)->first();
            abort_if($before === null, 404);
            abort_if(DB::table('tramite_borradores')->where('plantilla_id', $plantilla->id)->exists(), 409);

            DB::table('tramite_plantilla_campos')->where('id', $field)->update([
                'etiqueta' => $data['etiqueta'],
                'grupo' => $data['grupo'],
                'tipo_campo' => $data['tipo_campo'],
                'obligatorio' => (bool) $data['obligatorio'],
                'requiere_confirmacion' => (bool) $data['requiere_confirmacion'],
                'permite_html' => (bool) $data['permite_html'],
                'longitud_maxima' => $data['longitud_maxima'] ?? null,
                'texto_ayuda' => ($data['texto_ayuda'] ?? '') ?: null,
                'activo' => (bool) $data['activo'],
                'updated_at' => now(),
            ]);
            DB::table('tramite_config_events')->insert([
                'actor_id' => $request->user()->id,
                'accion' => 'edicion_campo_plantilla',
                'entidad' => 'campo_plantilla',
                'entidad_id' => $field,
                'valor_anterior' => json_encode(['etiqueta' => $before->etiqueta, 'orden' => $before->orden], JSON_THROW_ON_ERROR),
                'valor_nuevo' => json_encode(['etiqueta' => $data['etiqueta'], 'tipo_campo' => $data['tipo_campo']], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return to_route('admin.templates.fields', $plantilla)->with('success', 'Campo de plantilla actualizado.');
    }

    public function moveField(Request $request, TramitePlantilla $plantilla, int $field): RedirectResponse
    {
        $data = $request->validate(['direccion' => ['required', Rule::in(['up', 'down'])]]);

        DB::transaction(function () use ($request, $plantilla, $field, $data): void {
            abort_if(DB::table('tramite_borradores')->where('plantilla_id', $plantilla->id)->exists(), 409);
            $current = DB::table('tramite_plantilla_campos')->where('plantilla_id', $plantilla->id)
                ->where('id', $field)->first(['id', 'orden']);
            abort_if($current === null, 404);
            $fields = DB::table('tramite_plantilla_campos')->where('plantilla_id', $plantilla->id)
                ->orderBy('orden')->orderBy('id')->get(['id', 'orden'])->all();
            $index = array_search($field, array_map(static fn ($row): int => (int) $row->id, $fields), true);
            if ($index === false) {
                return;
            }
            $next = $index + ($data['direccion'] === 'up' ? -1 : 1);

            if (! isset($fields[$next])) {
                return;
            }

            DB::table('tramite_plantilla_campos')->where('id', $field)->update([
                'orden' => $fields[$next]->orden, 'updated_at' => now(),
            ]);
            DB::table('tramite_plantilla_campos')->where('id', $fields[$next]->id)->update([
                'orden' => $current->orden, 'updated_at' => now(),
            ]);
            DB::table('tramite_config_events')->insert([
                'actor_id' => $request->user()->id,
                'accion' => 'reordenamiento_campo_plantilla',
                'entidad' => 'campo_plantilla',
                'entidad_id' => $field,
                'valor_anterior' => json_encode(['orden' => $current->orden], JSON_THROW_ON_ERROR),
                'valor_nuevo' => json_encode(['orden' => $fields[$next]->orden], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return to_route('admin.templates.fields', $plantilla)->with('success', 'Orden de campos actualizado.');
    }

    private function sanitizeContent(string $content): string
    {
        $content = preg_replace('~<(script|style|iframe|object|embed|form)[^>]*>.*?</\\1\s*>~is', '', $content) ?? '';
        $content = strip_tags($content, '<article><header><section><h1><h2><p><br><strong><em><ul><ol><li><footer><div><hr>');
        $content = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\x27[^\x27]*\x27|[^\s>]+)/iu', '', $content) ?? '';
        $content = preg_replace('/\s(?:style|class|id)\s*=\s*("[^"]*"|\x27[^\x27]*\x27|[^\s>]+)/iu', '', $content) ?? '';

        return trim($content);
    }

    private function assertEssentialVariables(string $contenido): void
    {
        if (! str_contains($contenido, '{{NUMERO_DOCUMENTO_PREVIO}}') || ! str_contains($contenido, '{{CONTENIDO_PRINCIPAL}}')) {
            throw ValidationException::withMessages([
                'contenido' => 'La plantilla debe contener {{NUMERO_DOCUMENTO_PREVIO}} y {{CONTENIDO_PRINCIPAL}}.',
            ]);
        }
    }

    private function cloneFields(TramitePlantilla $origen, TramitePlantilla $destino): void
    {
        $campos = DB::table('tramite_plantilla_campos')->where('plantilla_id', $origen->id)
            ->get()->map(fn ($campo): array => [
                'plantilla_id' => $destino->id,
                'clave_variable' => $campo->clave_variable,
                'etiqueta' => $campo->etiqueta,
                'grupo' => $campo->grupo,
                'tipo_campo' => $campo->tipo_campo,
                'valor_predeterminado' => $campo->valor_predeterminado,
                'fuente_automatica' => $campo->fuente_automatica,
                'obligatorio' => $campo->obligatorio,
                'requiere_confirmacion' => $campo->requiere_confirmacion,
                'permite_html' => $campo->permite_html,
                'orden' => $campo->orden,
                'opciones' => $campo->opciones,
                'longitud_maxima' => $campo->longitud_maxima,
                'reglas_validacion' => $campo->reglas_validacion,
                'texto_ayuda' => $campo->texto_ayuda,
                'activo' => $campo->activo,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all();

        if ($campos !== []) {
            DB::table('tramite_plantilla_campos')->insert($campos);
        }
    }
}
