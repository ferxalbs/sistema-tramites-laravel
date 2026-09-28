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
use Illuminate\Support\Facades\Storage;
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
                'requiere_firma_fisica' => $ronda->versionBorrador->plantilla->requiere_firma_fisica,
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

    public function download(Request $request, Tramite $tramite, TramiteDocumentoFinal $documento): BinaryFileResponse
    {
        abort_unless((int) $documento->tramite_id === (int) $tramite->id, 404);
        abort_unless($documento->estado === 'emitido' && $documento->activo && $documento->disco === 'local', 404);

        $actor = $request->user();
        $authorized = in_array($actor->rol, ['asistente', 'administrador'], true)
            || ($actor->rol === 'estudiante'
                && (int) $tramite->propietario_id === (int) $actor->id)
            || ($actor->rol === 'docente'
                && (int) $documento->rondaRevision()->value('revisor_id') === (int) $actor->id);

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
