<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTramiteCierreRequest;
use App\Http\Requests\StoreTramiteConfirmacionRequest;
use App\Http\Requests\StoreTramiteEntregaRequest;
use App\Http\Requests\StoreTramiteFirmaRequest;
use App\Models\Tramite;
use App\Models\TramiteDocumentoFinal;
use App\Models\TramiteEvento;
use App\Models\TramiteEvidenciaEntrega;
use App\Models\TramiteFirma;
use App\Models\TramiteInformeCierre;
use App\Models\TramiteMedioEntrega;
use App\Models\TramiteRondaRevision;
use App\Models\User;
use App\Services\Tramites\ProcessTramiteDelivery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class TramiteEntregaController extends Controller
{
    public function show(Tramite $tramite): InertiaResponse
    {
        $documento = $tramite->documentoFinalActual()
            ->where('estado', 'emitido')
            ->with(['firma', 'rondaRevision'])
            ->first();
        $firma = $documento?->firma;
        $entrega = $tramite->entregaActual()->with(['medio', 'evidencias'])->first();
        $cierre = $tramite->cierre()->with('informe')->first();
        $informe = $cierre?->informe;

        return Inertia::render('tramites/entrega', [
            'tramite' => [
                'id' => $tramite->id,
                'codigo' => $tramite->codigo,
                'asunto' => $tramite->asunto,
                'estado' => $tramite->estado,
                'estado_label' => config('tramites.estados.'.$tramite->estado, $tramite->estado),
            ],
            'documento' => $documento === null ? null : [
                'id' => $documento->id,
                'numero' => $documento->numero_documento,
                'requiere_firma' => (bool) ($documento->contenido_snapshot['requiere_firma_fisica'] ?? false),
                'permite_no_firma' => (bool) ($documento->contenido_snapshot['permite_no_firma'] ?? false),
            ],
            'firma' => $firma === null ? null : [
                'no_requiere_firma' => $firma->no_requiere_firma,
                'fecha_firma' => $firma->fecha_firma?->toIso8601String(),
                'observacion' => $firma->observacion,
                'nombre_original' => $firma->nombre_original,
                'sha256' => $firma->sha256,
                'url_descarga' => $firma->ruta === null ? null : route('tramites.firmas.descargar', [$tramite->id, $firma->id]),
            ],
            'entrega' => $entrega === null ? null : [
                'id' => $entrega->id,
                'medio' => $entrega->medio->nombre,
                'tipo_medio' => $entrega->medio->tipo,
                'receptor_nombre' => $entrega->receptor_nombre,
                'receptor_documento' => $entrega->receptor_documento,
                'receptor_tipo' => $entrega->receptor_tipo,
                'correo_destino' => $entrega->correo_destino,
                'medio_utilizado' => $entrega->medio_utilizado,
                'fecha_entrega' => $entrega->fecha_entrega?->toIso8601String(),
                'confirmado' => $entrega->confirmado,
                'confirmado_por_estudiante' => $entrega->confirmado_por_estudiante,
                'observaciones' => $entrega->observaciones,
                'evidencias' => $entrega->evidencias->map(fn (TramiteEvidenciaEntrega $evidencia): array => [
                    'id' => $evidencia->id,
                    'tipo' => $evidencia->tipo_evidencia,
                    'nombre_original' => $evidencia->nombre_original,
                    'sha256' => $evidencia->sha256,
                    'url_descarga' => $evidencia->ruta === null
                        ? null
                        : route('tramites.evidencias.descargar', [$tramite->id, $evidencia->id]),
                ])->all(),
            ],
            'cierre' => $cierre === null ? null : [
                'resumen' => $cierre->resumen,
                'observacion' => $cierre->observacion,
                'fecha_cierre' => $cierre->fecha_cierre?->toIso8601String(),
                'informe' => $informe === null ? null : [
                    'id' => $informe->id,
                    'nombre_archivo' => $informe->nombre_archivo,
                    'sha256' => $informe->sha256,
                    'numero_paginas' => $informe->numero_paginas,
                    'codigo_verificacion' => $informe->codigo_verificacion,
                    'url_descarga' => route('tramites.informes-cierre.descargar', [$tramite->id, $informe->id]),
                ],
            ],
            'medios' => TramiteMedioEntrega::query()
                ->where('activo', true)
                ->orderBy('nombre')
                ->get(['id', 'codigo', 'nombre', 'tipo', 'requiere_evidencia'])
                ->map(fn (TramiteMedioEntrega $medio): array => [
                    'id' => $medio->id,
                    'codigo' => $medio->codigo,
                    'nombre' => $medio->nombre,
                    'tipo' => $medio->tipo,
                    'requiere_evidencia' => $medio->requiere_evidencia,
                ])->all(),
            'fecha_actual' => now()->format('Y-m-d\\TH:i'),
            'puede_preparar' => $tramite->estado === 'documento_final_generado',
            'puede_registrar_firma' => $tramite->estado === 'pendiente_firma',
            'puede_registrar_entrega' => $tramite->estado === 'listo_entrega' && $entrega === null,
            'puede_cerrar' => $tramite->estado === 'entregado' && $entrega?->confirmado,
        ]);
    }

    public function prepare(Request $request, Tramite $tramite, ProcessTramiteDelivery $workflow): RedirectResponse
    {
        $workflow->prepare($tramite, $this->actor($request));

        return redirect()->route('tramites.entrega.show', $tramite)->with('success', 'El documento fue preparado para entrega.');
    }

    public function registerSignature(StoreTramiteFirmaRequest $request, Tramite $tramite, ProcessTramiteDelivery $workflow): RedirectResponse
    {
        $workflow->registerSignature($tramite, $this->actor($request), $request->validated(), $request->file('evidencia'));

        return redirect()->route('tramites.entrega.show', $tramite)->with('success', 'La firma quedó registrada.');
    }

    public function registerDelivery(StoreTramiteEntregaRequest $request, Tramite $tramite, ProcessTramiteDelivery $workflow): RedirectResponse
    {
        $workflow->registerDelivery($tramite, $this->actor($request), $request->validated(), $request->file('evidencia'));

        return redirect()->route('tramites.entrega.show', $tramite)->with('success', 'La entrega quedó registrada y espera la confirmación de recepción.');
    }

    public function confirm(StoreTramiteConfirmacionRequest $request, Tramite $tramite, ProcessTramiteDelivery $workflow): RedirectResponse
    {
        $workflow->confirmDelivery($tramite, $this->actor($request), $request->validated('observacion'));

        return redirect()->back()->with('success', 'La recepción del documento fue confirmada.');
    }

    public function close(StoreTramiteCierreRequest $request, Tramite $tramite, ProcessTramiteDelivery $workflow): RedirectResponse
    {
        try {
            $informe = $workflow->close($tramite, $this->actor($request), $request->validated());
        } catch (HttpExceptionInterface $exception) {
            throw $exception;
        } catch (RuntimeException $exception) {
            return back()->withErrors(['cierre' => $exception->getMessage()]);
        }

        return redirect()->route('tramites.entrega.show', $tramite)
            ->with('success', 'El expediente fue cerrado y se generó su informe.');
    }

    public function downloadReport(Request $request, Tramite $tramite, TramiteInformeCierre $informe, ProcessTramiteDelivery $workflow): BinaryFileResponse
    {
        abort_unless((int) $informe->tramite_id === (int) $tramite->id && $informe->activo && $informe->disco === 'local', 404);
        $actor = $this->actor($request);
        $this->authorizeTramiteView($tramite, $actor, (int) $informe->cierre?->entrega?->documento_final_id);
        $path = $workflow->verifiedPrivatePath($informe->ruta, $informe->sha256, true);
        $this->registrarDescarga($tramite, $actor, 'descarga_informe_cierre', 'Se descargó el informe PDF de cierre.', $informe->id, $informe->sha256);

        return $this->privateDownload($path, $informe->nombre_archivo);
    }

    public function downloadSignature(Request $request, Tramite $tramite, TramiteFirma $firma, ProcessTramiteDelivery $workflow): BinaryFileResponse
    {
        abort_unless((int) $firma->tramite_id === (int) $tramite->id && $firma->disco === 'local' && is_string($firma->ruta) && is_string($firma->sha256), 404);
        $actor = $this->actor($request);
        $this->authorizeTramiteView($tramite, $actor, (int) $firma->documento_final_id);
        $path = $workflow->verifiedPrivatePath($firma->ruta, $firma->sha256, str_ends_with(strtolower($firma->ruta), '.pdf'));
        $this->registrarDescarga($tramite, $actor, 'descarga_evidencia_firma', 'Se descargó evidencia de firma.', $firma->id, $firma->sha256);

        return $this->privateDownload($path, $firma->nombre_original ?? basename($firma->ruta));
    }

    public function downloadEvidence(Request $request, Tramite $tramite, TramiteEvidenciaEntrega $evidencia, ProcessTramiteDelivery $workflow): BinaryFileResponse
    {
        abort_unless((int) $evidencia->tramite_id === (int) $tramite->id && $evidencia->activa && $evidencia->disco === 'local' && is_string($evidencia->ruta) && is_string($evidencia->sha256), 404);
        $actor = $this->actor($request);
        $this->authorizeTramiteView($tramite, $actor, (int) $evidencia->documento_final_id);
        $path = $workflow->verifiedPrivatePath($evidencia->ruta, $evidencia->sha256, str_ends_with(strtolower($evidencia->ruta), '.pdf'));
        $this->registrarDescarga($tramite, $actor, 'descarga_evidencia_entrega', 'Se descargó evidencia de entrega.', $evidencia->id, $evidencia->sha256);

        return $this->privateDownload($path, $evidencia->nombre_original ?? basename($evidencia->ruta));
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }

    private function authorizeTramiteView(Tramite $tramite, User $actor, int $documentoId): void
    {
        $borradorId = $documentoId > 0
            ? TramiteDocumentoFinal::query()->whereKey($documentoId)->value('borrador_id')
            : null;
        $autorizado = in_array($actor->rol, ['asistente', 'administrador'], true)
            || ($actor->rol === 'estudiante' && (int) $tramite->propietario_id === (int) $actor->id)
            || ($actor->rol === 'docente' && $borradorId !== null && TramiteRondaRevision::query()
                ->where('tramite_id', $tramite->id)
                ->where('revisor_id', $actor->id)
                ->where('borrador_id', $borradorId)
                ->exists());

        if (! $autorizado) {
            TramiteEvento::query()->create([
                'tramite_id' => $tramite->id,
                'usuario_id' => $actor->id,
                'accion' => 'acceso_entrega_no_autorizado',
                'descripcion' => 'Se rechazó un acceso no autorizado a información de entrega o cierre.',
            ]);

            abort(403);
        }
    }

    private function registrarDescarga(Tramite $tramite, User $actor, string $accion, string $descripcion, int $entityId, string $sha256): void
    {
        TramiteEvento::query()->create([
            'tramite_id' => $tramite->id,
            'usuario_id' => $actor->id,
            'accion' => $accion,
            'descripcion' => $descripcion,
            'metadatos' => ['registro_id' => $entityId, 'sha256' => $sha256],
        ]);
    }

    private function privateDownload(string $path, string $filename): BinaryFileResponse
    {
        $response = response()->download($path, $filename, ['X-Content-Type-Options' => 'nosniff']);
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }
}
