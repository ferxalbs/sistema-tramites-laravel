<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $nameParts = preg_split('/\s+/', trim($user->name), 2) ?: [];
        $profile = null;

        if ($user->rol === 'estudiante') {
            $student = $user->perfilEstudiante()->with('programa')->firstOrFail();
            $profile = [
                'programa' => $student->programa?->nombre,
                'condicion' => $student->condicion_academica,
                'ciclo_actual' => $student->ciclo_actual,
                'anio_egreso' => $student->anio_egreso,
                'direccion_residencia' => $student->direccion_residencia,
            ];
        } elseif ($user->rol === 'docente') {
            $teacher = $user->perfilDocente()->with('programa')->firstOrFail();
            $profile = [
                'codigo' => $teacher->codigo_docente,
                'programa' => $teacher->programa?->nombre,
                'especialidad' => $teacher->especialidad,
                'condicion_laboral' => $teacher->condicion_laboral,
            ];
        }

        return Inertia::render('settings/profile', [
            'status' => $request->session()->get('status'),
            'identity' => [
                'name' => $user->name,
                'nombres' => $user->nombres ?? ($nameParts[0] ?? $user->name),
                'apellidos' => $user->apellidos ?? ($nameParts[1] ?? ''),
                'email' => $user->email,
                'rol' => $user->rol,
                'dni' => $user->dni,
                'celular' => $user->celular,
                'correo_alternativo' => $user->correo_alternativo,
                'cuenta_provisional' => $user->cuenta_provisional,
                'firma_registrada' => in_array($user->rol, ['estudiante', 'docente', 'administrador'], true)
                    && Storage::disk('local')->exists($this->signaturePath($user)),
            ],
            'profile' => $profile,
        ]);
    }

    public function uploadSignature(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        abort_unless(in_array($user->rol, ['estudiante', 'docente', 'administrador'], true), 403);

        $data = $request->validate([
            'firma' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:4096'],
            'confirmar_uso' => ['required', 'accepted'],
        ]);

        $bytes = file_get_contents($data['firma']->getRealPath());
        $source = is_string($bytes) ? imagecreatefromstring($bytes) : false;

        if ($source === false) {
            throw ValidationException::withMessages(['firma' => 'No se pudo leer la imagen de la firma.']);
        }

        $width = imagesx($source);
        $height = imagesy($source);

        if ($width < 40 || $height < 20 || $width > 4000 || $height > 4000) {
            imagedestroy($source);
            throw ValidationException::withMessages(['firma' => 'La imagen debe contener solo la firma y tener un tamaño razonable.']);
        }

        $scale = min(1, 1600 / $width, 600 / $height);
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));
        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        $blanco = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $blanco);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
        ob_start();
        imagejpeg($canvas, null, 92);
        $jpeg = ob_get_clean();
        imagedestroy($source);
        imagedestroy($canvas);

        if (! is_string($jpeg) || $jpeg === '') {
            throw ValidationException::withMessages(['firma' => 'No se pudo preparar la imagen de la firma.']);
        }

        if (! Storage::disk('local')->put($this->signaturePath($user), $jpeg)) {
            throw ValidationException::withMessages(['firma' => 'No se pudo guardar la firma en el almacenamiento privado.']);
        }

        DB::table('user_account_events')->insert([
            'user_id' => $user->id,
            'actor_id' => $user->id,
            'accion' => 'update_signature',
            'estado_anterior' => $user->estado_cuenta,
            'estado_nuevo' => $user->estado_cuenta,
            'motivo' => 'Se actualizó la firma escaneada del perfil.',
            'created_at' => now(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Firma guardada en tu perfil.']);

        return to_route('profile.edit');
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $data = $request->validated();

        DB::transaction(function () use ($user, $data): void {
            $user->update([
                'name' => trim($data['nombres'].' '.($data['apellidos'] ?? '')),
                'nombres' => $data['nombres'],
                'apellidos' => ($data['apellidos'] ?? '') !== '' ? $data['apellidos'] : null,
                'celular' => $data['celular'],
                'correo_alternativo' => $data['correo_alternativo'] ?: null,
            ]);

            if ($user->rol === 'estudiante') {
                $user->perfilEstudiante()->firstOrFail()->update([
                    'direccion_residencia' => $data['direccion_residencia'] ?: null,
                ]);
            } elseif ($user->rol === 'docente') {
                $user->perfilDocente()->firstOrFail()->update([
                    'especialidad' => $data['especialidad'] ?: null,
                    'condicion_laboral' => $data['condicion_laboral'] ?: null,
                ]);
            }

            DB::table('user_account_events')->insert([
                'user_id' => $user->id,
                'actor_id' => $user->id,
                'accion' => 'edit_profile',
                'estado_anterior' => $user->estado_cuenta,
                'estado_nuevo' => $user->estado_cuenta,
                'motivo' => null,
                'created_at' => now(),
            ]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Datos de perfil actualizados.']);

        return to_route('profile.edit');
    }

    private function signaturePath(User $user): string
    {
        return 'firmas-perfil/'.$user->id.'.jpg';
    }
}
