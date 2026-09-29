<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdminUserRequest;
use App\Models\PerfilDocente;
use App\Models\PerfilEstudiante;
use App\Models\ProgramaEstudio;
use App\Models\User;
use App\Notifications\AdministrativeResetPassword;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Throwable;

class UserAccountController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
            'rol' => ['nullable', Rule::in(['estudiante', 'asistente', 'docente', 'administrador'])],
            'estado' => ['nullable', Rule::in(['activo', 'pendiente', 'inactivo', 'rechazado'])],
            'selected' => ['nullable', 'integer', 'exists:users,id'],
        ]);
        $query = User::query();
        $search = trim($filters['q'] ?? '');

        if ($search !== '') {
            $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
            $query->where(fn (Builder $users): Builder => $users
                ->whereRaw("name LIKE ? ESCAPE '!'", [$like])
                ->orWhereRaw("email LIKE ? ESCAPE '!'", [$like]));
        }

        $users = $query
            ->when(($filters['rol'] ?? '') !== '', fn (Builder $users): Builder => $users->where('rol', $filters['rol']))
            ->when(($filters['estado'] ?? '') !== '', fn (Builder $users): Builder => $users->where('estado_cuenta', $filters['estado']))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(10)
            ->through(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'rol' => $user->rol,
                'estado' => $user->estado_cuenta,
                'created_at' => $user->created_at?->toDateString(),
            ])->withQueryString();

        $selected = isset($filters['selected']) ? User::query()->find($filters['selected']) : null;

        return Inertia::render('usuarios', [
            'users' => $users,
            'viewerId' => $request->user()->id,
            'filters' => ['q' => $search, 'rol' => $filters['rol'] ?? '', 'estado' => $filters['estado'] ?? ''],
            'selected' => $selected instanceof User ? [
                'id' => $selected->id,
                'name' => $selected->name,
                'email' => $selected->email,
                'rol' => $selected->rol,
                'estado' => $selected->estado_cuenta,
                'motivo' => $selected->motivo_inactivacion,
                'events' => DB::table('user_account_events')
                    ->leftJoin('users as actors', 'actors.id', '=', 'user_account_events.actor_id')
                    ->where('user_account_events.user_id', $selected->id)
                    ->orderByDesc('user_account_events.id')
                    ->limit(20)
                    ->get(['user_account_events.accion', 'user_account_events.estado_anterior', 'user_account_events.estado_nuevo', 'user_account_events.motivo', 'user_account_events.created_at', 'actors.name as actor'])
                    ->all(),
            ] : null,
            'counts' => User::query()->select('estado_cuenta')->selectRaw('COUNT(*) as total')->groupBy('estado_cuenta')->get()
                ->mapWithKeys(fn (User $user): array => [$user->estado_cuenta => (int) $user->getAttribute('total')])->all(),
        ]);
    }

    public function create(): InertiaResponse
    {
        return Inertia::render('usuarios-form', [
            'user' => null,
            'programas' => ProgramaEstudio::query()->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function store(AdminUserRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        $user = DB::transaction(function () use ($data, $actor): User {
            $active = (bool) ($data['activar_inmediatamente'] ?? false);
            $user = User::create([
                'name' => $data['nombres'].' '.$data['apellidos'],
                'nombres' => $data['nombres'],
                'apellidos' => $data['apellidos'],
                'dni' => $data['dni'],
                'celular' => $data['celular'],
                'email' => $data['email'],
                'correo_alternativo' => $data['correo_alternativo'] ?? null,
                'password' => $data['password'],
                'rol' => $data['rol'],
                'activo' => $active,
                'estado_cuenta' => $active ? 'activo' : 'pendiente',
                'debe_cambiar_password' => true,
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();

            $this->syncProfile($user, $data);
            $this->recordEvent($user, $actor, 'create', 'nuevo', $user->estado_cuenta);

            return $user;
        });

        return to_route('admin.users.index', ['selected' => $user->id])
            ->with('success', 'Cuenta creada. La contraseña temporal deberá cambiarse al iniciar sesión.');
    }

    public function edit(User $user): InertiaResponse
    {
        $user->load(['perfilEstudiante', 'perfilDocente']);
        $student = $user->perfilEstudiante;
        $teacher = $user->perfilDocente;

        return Inertia::render('usuarios-form', [
            'user' => [
                'id' => $user->id,
                'rol' => $user->rol,
                'nombres' => $user->nombres,
                'apellidos' => $user->apellidos,
                'dni' => $user->dni,
                'celular' => $user->celular,
                'email' => $user->email,
                'correo_alternativo' => $user->correo_alternativo,
                'codigo_estudiante' => $student?->codigo_estudiante,
                'codigo_docente' => $teacher?->codigo_docente,
                'programa_estudio_id' => $student instanceof PerfilEstudiante ? $student->programa_estudio_id : $teacher?->programa_estudio_id,
                'condicion_academica' => $student?->condicion_academica,
                'ciclo_actual' => $student?->ciclo_actual,
                'anio_egreso' => $student?->anio_egreso,
                'direccion_residencia' => $student?->direccion_residencia,
                'especialidad' => $teacher?->especialidad,
                'condicion_laboral' => $teacher?->condicion_laboral,
            ],
            'programas' => ProgramaEstudio::query()->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'passwordRules' => null,
        ]);
    }

    public function save(AdminUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        DB::transaction(function () use ($actor, $user, $data): void {
            $current = User::query()->findOrFail($user->id);
            $roleChanged = $current->rol !== $data['rol'];

            if ($roleChanged && $current->id === $actor->id) {
                throw ValidationException::withMessages(['rol' => 'No puede cambiar su propio rol durante la sesión activa.']);
            }

            $update = User::query()->whereKey($current->id)
                ->where('rol', $current->rol)
                ->where('sesion_version', $current->sesion_version);

            if ($roleChanged) {
                $update->whereRaw('NOT EXISTS (SELECT 1 FROM tramite_asignaciones WHERE revisor_id = users.id AND activa = 1)');

                if ($current->rol === 'administrador' && $current->activo && $current->estado_cuenta === 'activo') {
                    $update->whereRaw("(SELECT COUNT(*) FROM users WHERE rol = 'administrador' AND activo = 1 AND estado_cuenta = 'activo') > 1");
                }
            }

            $changed = $update->update([
                'name' => $data['nombres'].' '.$data['apellidos'],
                'nombres' => $data['nombres'],
                'apellidos' => $data['apellidos'],
                'dni' => $data['dni'],
                'celular' => $data['celular'],
                'email' => $data['email'],
                'correo_alternativo' => $data['correo_alternativo'] ?? null,
                'rol' => $data['rol'],
                'sesion_version' => $current->sesion_version + (int) $roleChanged,
                'remember_token' => $roleChanged ? Str::random(60) : $current->remember_token,
            ]);

            if ($changed !== 1) {
                throw ValidationException::withMessages(['rol' => 'No se pudo cambiar el rol; verifique asignaciones y administradores activos.']);
            }

            $current->refresh();
            $this->syncProfile($current, $data);
            $this->recordEvent($current, $actor, 'edit', $current->estado_cuenta, $current->estado_cuenta);

            if ($roleChanged) {
                $this->recordEvent($current, $actor, 'change_role', $current->estado_cuenta, $current->estado_cuenta,
                    'Rol '.$user->rol.' → '.$data['rol']);
                DB::table('sessions')->where('user_id', $current->id)->delete();
            }
        });

        return to_route('admin.users.index', ['selected' => $user->id])->with('success', 'Usuario actualizado.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $request->merge(['motivo' => trim((string) $request->input('motivo', ''))]);
        $input = $request->validate([
            'accion' => ['required', Rule::in(['activate', 'deactivate', 'reject'])],
            'motivo' => ['nullable', 'string', 'max:500', Rule::requiredIf(in_array($request->input('accion'), ['deactivate', 'reject'], true))],
        ]);
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        DB::transaction(function () use ($actor, $user, $input): void {
            $current = User::query()->findOrFail($user->id);
            $action = $input['accion'];
            $newState = match ($action) {
                'activate' => 'activo',
                'deactivate' => 'inactivo',
                'reject' => 'rechazado',
                default => throw ValidationException::withMessages(['accion' => 'Acción no permitida.']),
            };

            if ($current->id === $actor->id && $action !== 'activate') {
                throw ValidationException::withMessages(['accion' => 'No puede desactivar ni rechazar su propia cuenta.']);
            }

            if (($action === 'deactivate' && $current->estado_cuenta !== 'activo')
                || ($action === 'reject' && $current->estado_cuenta !== 'pendiente')
                || ($action === 'activate' && $current->estado_cuenta === 'activo')) {
                throw ValidationException::withMessages(['accion' => 'La cuenta cambió de estado o la transición no está permitida.']);
            }

            $update = User::query()->whereKey($current->id)
                ->where('estado_cuenta', $current->estado_cuenta)
                ->where('sesion_version', $current->sesion_version);

            if ($current->rol === 'administrador' && $action === 'deactivate') {
                $update->whereRaw("(SELECT COUNT(*) FROM users WHERE rol = 'administrador' AND activo = 1 AND estado_cuenta = 'activo') > 1");
            }

            $changed = $update->update([
                'estado_cuenta' => $newState,
                'activo' => $newState === 'activo',
                'motivo_inactivacion' => $newState === 'activo' ? null : ($input['motivo'] ?? null),
                'email_verified_at' => $newState === 'activo' ? ($current->email_verified_at ?? now()) : $current->email_verified_at,
                'sesion_version' => $current->sesion_version + 1,
                'remember_token' => Str::random(60),
            ]);

            if ($changed !== 1) {
                throw ValidationException::withMessages(['accion' => 'No se pudo actualizar la cuenta; verifique que no sea el último administrador activo.']);
            }

            DB::table('user_account_events')->insert([
                'user_id' => $current->id,
                'actor_id' => $actor->id,
                'accion' => $action,
                'estado_anterior' => $current->estado_cuenta,
                'estado_nuevo' => $newState,
                'motivo' => $input['motivo'] ?? null,
                'created_at' => now(),
            ]);
            DB::table('sessions')->where('user_id', $current->id)->delete();
        });

        return back()->with('success', 'Estado de cuenta actualizado.');
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        $user->refresh();

        if (! $user->activo || $user->estado_cuenta !== 'activo' || ! str_ends_with($user->email, '@seoane.edu.pe')) {
            throw ValidationException::withMessages(['reset' => 'La cuenta debe estar activa y tener correo institucional.']);
        }

        $token = PasswordBroker::broker(config('fortify.passwords'))->createToken($user);

        try {
            $user->notify(new AdministrativeResetPassword($token));
            $sent = true;
        } catch (Throwable) {
            $sent = false;
        }

        $this->recordEvent($user, $actor, 'reset_link', $user->estado_cuenta, $user->estado_cuenta,
            $sent ? 'Enlace enviado al correo institucional.' : 'El correo no pudo enviarse.');

        Inertia::flash('toast', [
            'type' => $sent ? 'success' : 'error',
            'message' => $sent
                ? 'Se generó y envió un enlace seguro de restablecimiento.'
                : 'El enlace no pudo enviarse. Revise la configuración de correo y reintente.',
        ]);

        return back();
    }

    /** @param array<string, mixed> $data */
    private function syncProfile(User $user, array $data): void
    {
        if ($data['rol'] === 'estudiante') {
            PerfilEstudiante::query()->updateOrCreate(['user_id' => $user->id], [
                'codigo_estudiante' => $data['codigo_estudiante'],
                'programa_estudio_id' => $data['programa_estudio_id'],
                'condicion_academica' => $data['condicion_academica'],
                'ciclo_actual' => $data['condicion_academica'] === 'Estudiante' ? $data['ciclo_actual'] : null,
                'anio_egreso' => $data['condicion_academica'] === 'Egresado' ? $data['anio_egreso'] : null,
                'direccion_residencia' => $data['direccion_residencia'] ?? null,
            ]);
            PerfilDocente::query()->where('user_id', $user->id)->delete();
        } elseif ($data['rol'] === 'docente') {
            PerfilDocente::query()->updateOrCreate(['user_id' => $user->id], [
                'codigo_docente' => $data['codigo_docente'] ?? null,
                'programa_estudio_id' => $data['programa_estudio_id'],
                'especialidad' => $data['especialidad'] ?? null,
                'condicion_laboral' => $data['condicion_laboral'] ?? null,
            ]);
            PerfilEstudiante::query()->where('user_id', $user->id)->delete();
        } else {
            PerfilEstudiante::query()->where('user_id', $user->id)->delete();
            PerfilDocente::query()->where('user_id', $user->id)->delete();
        }
    }

    private function recordEvent(User $user, User $actor, string $action, string $before, string $after, ?string $reason = null): void
    {
        DB::table('user_account_events')->insert([
            'user_id' => $user->id,
            'actor_id' => $actor->id,
            'accion' => $action,
            'estado_anterior' => $before,
            'estado_nuevo' => $after,
            'motivo' => $reason,
            'created_at' => now(),
        ]);
    }
}
