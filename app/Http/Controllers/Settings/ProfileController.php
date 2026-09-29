<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        $profile = null;

        if ($user->rol === 'estudiante') {
            $student = $user->perfilEstudiante()->with('programa')->firstOrFail();
            $profile = [
                'codigo' => $student->codigo_estudiante,
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
                'email' => $user->email,
                'rol' => $user->rol,
                'dni' => $user->dni,
                'celular' => $user->celular,
                'correo_alternativo' => $user->correo_alternativo,
                'cuenta_provisional' => $user->cuenta_provisional,
            ],
            'profile' => $profile,
        ]);
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
                'celular' => $data['celular'],
                ...($user->rol === 'estudiante' ? ['correo_alternativo' => $data['correo_alternativo'] ?: null] : []),
            ]);

            if ($user->rol === 'estudiante') {
                $user->perfilEstudiante()->firstOrFail()->update([
                    'direccion_residencia' => $data['direccion_residencia'] ?: null,
                ]);
            } else {
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
}
