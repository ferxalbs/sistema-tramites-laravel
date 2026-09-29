<?php

namespace App\Http\Controllers;

use App\Models\PerfilDocente;
use App\Models\ProgramaEstudio;
use App\Models\User;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Throwable;

class TeacherAccessRequestController extends Controller
{
    private const ALLOWED_POSITIONS = ['asistente_laboratorio', 'docente', 'otro'];

    public function create(Request $request): InertiaResponse
    {
        return Inertia::render('auth/teacher-access-request', [
            'programas' => ProgramaEstudio::query()->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'cargos' => DB::table('cargos_institucionales')->where('activo', true)
                ->whereIn('codigo', self::ALLOWED_POSITIONS)->orderBy('nombre')->get(['id', 'nombre']),
            'status' => $request->session()->get('status'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'nombres' => trim((string) $request->input('nombres', '')),
            'apellidos' => trim((string) $request->input('apellidos', '')),
            'email' => mb_strtolower(trim((string) $request->input('email', ''))),
            'motivo' => trim((string) $request->input('motivo', '')),
        ]);
        $personName = static function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value) || preg_match("/^[\\p{L}\\p{M} .'-]+$/u", $value) !== 1) {
                $fail('Ingrese nombres y apellidos válidos.');
            }
        };
        $data = $request->validate([
            'nombres' => ['required', 'string', 'min:2', 'max:120', $personName],
            'apellidos' => ['required', 'string', 'min:2', 'max:120', $personName],
            'email' => [
                'required', 'email', 'max:190', 'regex:/@seoane\.edu\.pe\z/i',
                Rule::unique('users', 'email'), Rule::unique('users', 'correo_alternativo'),
            ],
            'programa_estudio_id' => ['required', 'integer', Rule::exists('programas_estudio', 'id')->where('activo', true)],
            'cargo_institucional_id' => ['required', 'integer', Rule::exists('cargos_institucionales', 'id')
                ->where('activo', true)->whereIn('codigo', self::ALLOWED_POSITIONS)],
            'motivo' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        $user = DB::transaction(function () use ($data): User {
            $user = User::query()->create([
                'name' => $data['nombres'].' '.$data['apellidos'],
                'nombres' => $data['nombres'],
                'apellidos' => $data['apellidos'],
                'email' => $data['email'],
                'password' => Hash::make(Str::random(64)),
                'rol' => 'docente',
                'cargo_institucional_id' => $data['cargo_institucional_id'],
                'activo' => false,
                'estado_cuenta' => 'pendiente',
                'debe_cambiar_password' => true,
                'cuenta_provisional' => true,
            ]);
            PerfilDocente::query()->create(['user_id' => $user->id, 'programa_estudio_id' => $data['programa_estudio_id']]);
            DB::table('teacher_access_requests')->insert([
                'user_id' => $user->id,
                'cargo_institucional_id' => $data['cargo_institucional_id'],
                'motivo' => $data['motivo'],
                'created_at' => now(),
            ]);

            return $user;
        });

        try {
            $user->sendEmailVerificationNotification();
        } catch (Throwable) {
            Log::warning('No se pudo enviar la verificación de una solicitud docente.');
        }

        return to_route('teacher-access.create')->with('status', 'Solicitud recibida. Verifique su correo institucional; la aprobación administrativa continuará siendo necesaria.');
    }
}
