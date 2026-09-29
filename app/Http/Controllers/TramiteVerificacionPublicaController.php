<?php

namespace App\Http\Controllers;

use App\Models\TramiteDocumentoFinal;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TramiteVerificacionPublicaController extends Controller
{
    public function show(Request $request): Response
    {
        $codigoRecibido = $request->query('codigo');
        $codigo = is_string($codigoRecibido) ? strtoupper(trim($codigoRecibido)) : '';
        $resultado = null;

        if ($codigoRecibido !== null) {
            $documento = null;

            if (preg_match('/^[A-F0-9]{4}(?:-[A-F0-9]{4}){3}$/', $codigo) === 1) {
                $documento = TramiteDocumentoFinal::query()
                    ->select([
                        'id',
                        'tramite_id',
                        'tipo_documento',
                        'numero_documento',
                        'codigo_verificacion',
                        'estado',
                        'activo',
                        'fecha_emision',
                    ])
                    ->with('tramite:id,codigo')
                    ->where('codigo_verificacion', $codigo)
                    ->first();

                if ($documento !== null && ! hash_equals((string) $documento->codigo_verificacion, $codigo)) {
                    $documento = null;
                }
            }

            $estado = match (true) {
                $documento === null => 'invalido',
                strtolower($documento->estado) === 'anulado' => 'anulado',
                strtolower($documento->estado) === 'sustituido' => 'sustituido',
                strtolower($documento->estado) === 'emitido' && $documento->activo => 'vigente',
                strtolower($documento->estado) === 'emitido' => 'sustituido',
                default => 'invalido',
            };

            $resultado = [
                'estado' => $estado,
                'documento' => in_array($estado, ['vigente', 'anulado', 'sustituido'], true) ? [
                    'tipo' => $documento->tipo_documento,
                    'numero' => $documento->numero_documento,
                    'expediente' => $documento->tramite?->codigo,
                    'fecha_emision' => $documento->fecha_emision?->toDateString(),
                    'codigo' => $documento->codigo_verificacion,
                ] : null,
            ];
        }

        return Inertia::render('documentos/verificacion-publica', [
            'codigo' => $codigo,
            'resultado' => $resultado,
            'institucion' => config('app.name'),
            'action' => route('documentos.verificar', [], false),
        ]);
    }
}
