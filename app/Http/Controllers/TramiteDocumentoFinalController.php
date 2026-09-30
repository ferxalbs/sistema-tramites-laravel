<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmitTramiteFinalDocumentRequest;
use App\Models\Tramite;
use App\Models\TramiteDocumentoFinal;
use App\Models\TramiteEvento;
use App\Models\TramiteRondaRevision;
use App\Models\User;
use App\Services\Tramites\GenerateTramiteFinalDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class TramiteDocumentoFinalController extends Controller
{
    public function preview(Tramite $tramite): InertiaResponse
    {
        abort_unless(in_array($tramite->estado, ['aprobado', 'rechazado'], true), 409);

        $ronda = TramiteRondaRevision::query()
            ->with(['versionBorrador.plantilla', 'versionBorrador.firmante'])
            ->where('tramite_id', $tramite->id)
            ->where('estado', $tramite->estado)
            ->where('activa', false)
            ->orderByDesc('numero_ronda')
            ->first();

        abort_unless($ronda?->versionBorrador !== null, 409);

        return Inertia::render('tramites/documento-final', [
            'tramite' => [
                'id' => $tramite->id,
                'codigo' => $tramite->codigo,
                'asunto' => $tramite->asunto,
                'estado' => $tramite->estado,
            ],
            'revision' => [
                'decision' => $ronda->estado,
                'conclusion' => $ronda->conclusion,
                'comentario_publico' => $ronda->comentario_publico,
                'numero_ronda' => $ronda->numero_ronda,
            ],
            'borrador' => [
                'version' => $ronda->versionBorrador->version,
                'plantilla' => $ronda->versionBorrador->plantilla->nombre,
                'asunto' => $ronda->versionBorrador->asunto,
                'fecha_documento' => $ronda->versionBorrador->fecha_documento->toDateString(),
                'firmante' => $ronda->versionBorrador->firmante?->name,
                'firma_perfil_registrada' => $ronda->versionBorrador->firmante !== null
                    && Storage::disk('local')->exists('firmas-perfil/'.$ronda->versionBorrador->firmante->id.'.jpg'),
            ],
        ]);
    }

    public function emit(EmitTramiteFinalDocumentRequest $request, Tramite $tramite, GenerateTramiteFinalDocument $generate): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        try {
            $documento = $generate->execute($tramite, $actor);
        } catch (HttpExceptionInterface $exception) {
            throw $exception;
        } catch (RuntimeException $exception) {
            return back()->withErrors(['documento' => $exception->getMessage()]);
        }

        return redirect()->route('tramites.show', $tramite)
            ->with('success', 'Se emitió el documento oficial '.$documento->numero_documento.'.');
    }

    public function annul(Request $request, Tramite $tramite, TramiteDocumentoFinal $documento): RedirectResponse
    {
        abort_unless((int) $documento->tramite_id === (int) $tramite->id, 404);
        $datos = $request->validate([
            'accion' => ['required', 'in:anular,sustituir'],
            'motivo' => ['required', 'string', 'min:10', 'max:1000'],
        ]);
        $motivo = trim($datos['motivo']);

        if (mb_strlen($motivo) < 10) {
            throw ValidationException::withMessages(['motivo' => 'El motivo debe tener al menos diez caracteres.']);
        }

        $estadoDocumento = $datos['accion'] === 'sustituir' ? 'sustituido' : 'anulado';

        DB::transaction(function () use ($request, $tramite, $documento, $motivo, $estadoDocumento): void {
            $registro = Tramite::query()->findOrFail($tramite->id);
            $actual = TramiteDocumentoFinal::query()->findOrFail($documento->id);
            $decision = $actual->rondaRevision()->value('estado');

            abort_unless($registro->estado === 'documento_final_generado'
                && (int) $actual->tramite_id === (int) $registro->id
                && $actual->estado === 'emitido'
                && $actual->activo
                && in_array($decision, ['aprobado', 'rechazado'], true), 409);

            $actualizados = DB::table('tramite_documentos_finales')
                ->where('id', $actual->id)
                ->where('tramite_id', $registro->id)
                ->where('estado', 'emitido')
                ->where('activo', true)
                ->update([
                    'estado' => $estadoDocumento,
                    'activo' => false,
                    'motivo_anulacion' => $motivo,
                    'fecha_anulacion' => now(),
                    'anulado_por' => $request->user()->id,
                    'updated_at' => now(),
                ]);
            abort_unless($actualizados === 1, 409);

            $actualizados = DB::table('tramite_numeraciones_documentales')
                ->where('id', $actual->numeracion_id)
                ->where('tramite_id', $registro->id)
                ->where('estado', 'emitida')
                ->update(['estado' => 'anulada', 'updated_at' => now()]);
            abort_unless($actualizados === 1, 409);

            $actualizados = DB::table('tramites')
                ->where('id', $registro->id)
                ->where('estado', 'documento_final_generado')
                ->update(['estado' => $decision, 'updated_at' => now()]);
            abort_unless($actualizados === 1, 409);

            TramiteEvento::query()->create([
                'tramite_id' => $registro->id,
                'usuario_id' => $request->user()->id,
                'accion' => $estadoDocumento === 'sustituido' ? 'sustitucion_autorizada' : 'documento_final_anulado',
                'descripcion' => $estadoDocumento === 'sustituido'
                    ? 'Se autorizó la sustitución del documento oficial '.$actual->numero_documento.'.'
                    : 'Se anuló el documento oficial '.$actual->numero_documento.'.',
                'estado_anterior' => 'documento_final_generado',
                'estado_nuevo' => $decision,
                'metadatos' => [
                    'documento_id' => $actual->id,
                    'numeracion_id' => $actual->numeracion_id,
                    'motivo' => $motivo,
                ],
            ]);
        });

        return redirect()->route('tramites.show', $tramite)
            ->with('success', 'El documento oficial quedó '.$estadoDocumento.'; su número no se reutilizará.');
    }

    public function download(Request $request, Tramite $tramite, TramiteDocumentoFinal $documento): BinaryFileResponse
    {
        abort_unless((int) $documento->tramite_id === (int) $tramite->id, 404);
        $vigente = $documento->estado === 'emitido' && $documento->activo;
        $historico = in_array($documento->estado, ['anulado', 'sustituido'], true) && ! $documento->activo;
        abort_unless(($vigente || $historico) && $documento->disco === 'local', 404);

        $actor = $request->user();
        $personal = in_array($actor->rol, ['asistente', 'administrador'], true);
        $revisorAsignado = $actor->rol === 'docente' && DB::table('tramite_rondas_revision as rondas')
            ->join('tramite_asignaciones as asignaciones', 'asignaciones.id', '=', 'rondas.asignacion_id')
            ->where('rondas.id', $documento->ronda_revision_id)
            ->where('rondas.tramite_id', $tramite->id)
            ->where('rondas.revisor_id', $actor->id)
            ->where('asignaciones.tramite_id', $tramite->id)
            ->where('asignaciones.revisor_id', $actor->id)
            ->where('asignaciones.destino', 'docente')
            ->exists();
        $authorized = $personal || $revisorAsignado;

        if (! $authorized) {
            TramiteEvento::query()->create([
                'tramite_id' => $tramite->id,
                'usuario_id' => $actor->id,
                'accion' => 'acceso_documento_final_no_autorizado',
                'descripcion' => 'Se rechazó el acceso no autorizado a un documento oficial.',
                'metadatos' => ['documento_id' => $documento->id],
            ]);

            abort(403);
        }

        $disk = Storage::disk('local');
        $root = realpath((string) config('filesystems.disks.local.root'));
        $path = realpath($disk->path((string) $documento->ruta));

        abort_unless(
            is_string($root)
                && is_string($path)
                && str_starts_with($path, rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)
                && is_file($path),
            404,
        );

        $header = file_get_contents($path, false, null, 0, 5);
        $hash = hash_file('sha256', $path);

        abort_unless(
            $header === '%PDF-'
                && is_string($hash)
                && is_string($documento->sha256)
                && hash_equals($documento->sha256, $hash),
            404,
        );

        TramiteEvento::query()->create([
            'tramite_id' => $tramite->id,
            'usuario_id' => $actor->id,
            'accion' => 'descarga_documento_final',
            'descripcion' => 'Se descargó el documento oficial '.$documento->numero_documento.'.',
            'metadatos' => ['documento_id' => $documento->id, 'sha256' => $hash],
        ]);

        $response = response()->download($path, (string) $documento->nombre_archivo, [
            'X-Content-Type-Options' => 'nosniff',
        ]);
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }
}
