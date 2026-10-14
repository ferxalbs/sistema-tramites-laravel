<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReadinessController extends Controller
{
    public function __invoke(): JsonResponse
    {
        try {
            DB::select('SELECT 1');
            $root = (string) config('filesystems.disks.local.root');
            $free = is_dir($root) ? disk_free_space($root) : false;
            if (! is_readable($root) || ! is_writable($root) || $free === false || $free < 10 * 1024 * 1024) {
                throw new \RuntimeException('El almacenamiento privado no está disponible o no tiene espacio suficiente.');
            }

            return response()->json(['status' => 'ok'])->header('Cache-Control', 'no-store');
        } catch (Throwable $exception) {
            Log::warning('Comprobación de disponibilidad fallida.', ['exception' => $exception::class]);

            return response()->json(['status' => 'unavailable'], 503)->header('Cache-Control', 'no-store');
        }
    }
}
