<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdminUserRequest;
use App\Models\PerfilDocente;
use App\Models\PerfilEstudiante;
use App\Models\ProgramaEstudio;
use App\Models\User;
use App\Notifications\AdministrativeResetPassword;
use App\Services\Tramites\LinkApplicantAccount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Support\Facades\Storage;
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
        ]);
        $query = User::query()->with('perfilEstudiante:id,user_id,condicion_academica');
        $search = trim($filters['q'] ?? '');

        if ($search !== '') {
            $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
            $query->where(fn (Builder $users): Builder => $users
                ->whereRaw("name LIKE ? ESCAPE '!'", [$like])
                ->orWhereRaw("email LIKE ? ESCAPE '!'", [$like])
                ->orWhereRaw("dni LIKE ? ESCAPE '!'", [$like]));
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
                'dni' => $user->dni,
                'rol' => $user->rol,
                'condicion_academica' => $user->perfilEstudiante?->condicion_academica,
                'estado' => $user->estado_cuenta,
                'created_at' => $user->created_at?->toDateString(),
            ])->withQueryString();

        return Inertia::render('usuarios', [
            'users' => $users,
            'filters' => ['q' => $search, 'rol' => $filters['rol'] ?? '', 'estado' => $filters['estado'] ?? ''],
            'counts' => User::query()->select('estado_cuenta')->selectRaw('COUNT(*) as total')->groupBy('estado_cuenta')->get()
                ->mapWithKeys(fn (User $user): array => [$user->estado_cuenta => (int) $user->getAttribute('total')])->all(),
        ]);
    }

    public function create(): InertiaResponse
    {
        return Inertia::render('usuarios-form', [
            'user' => null,
            'programas' => ProgramaEstudio::query()->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'cargos' => DB::table('cargos_institucionales')->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
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
                'cargo_institucional_id' => $data['rol'] === 'estudiante' ? null : ($data['cargo_institucional_id'] ?? null),
                'activo' => $active,
                'estado_cuenta' => $active ? 'activo' : 'pendiente',
                'debe_cambiar_password' => true,
                'cuenta_provisional' => false,
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();

            $this->syncProfile($user, $data);
            if ($active) {
                app(LinkApplicantAccount::class)->execute($user, $actor);
            }
            $this->recordEvent($user, $actor, 'create', 'nuevo', $user->estado_cuenta);

            return $user;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Cuenta creada. La contraseña temporal deberá cambiarse al iniciar sesión.']);

        return to_route('admin.users.edit', ['user' => $user->id]);
    }

    public function edit(Request $request, User $user): InertiaResponse
    {
        $user->load(['perfilEstudiante', 'perfilDocente']);
        $student = $user->perfilEstudiante;
        $teacher = $user->perfilDocente;
        $teacherRequest = $user->rol === 'docente'
            ? DB::table('teacher_access_requests as requests')
                ->leftJoin('cargos_institucionales as positions', 'positions.id', '=', 'requests.cargo_institucional_id')
                ->where('requests.user_id', $user->id)
                ->first(['requests.motivo', 'requests.created_at', 'positions.nombre as cargo'])
            : null;

        return Inertia::render('usuarios-form', [
            'user' => [
                'id' => $user->id,
                'estado' => $user->estado_cuenta,
                'motivo_inactivacion' => $user->motivo_inactivacion,
                'rol' => $user->rol,
                'nombres' => $user->nombres,
                'apellidos' => $user->apellidos,
                'dni' => $user->dni,
                'celular' => $user->celular,
                'email' => $user->email,
                'correo_alternativo' => $user->correo_alternativo,
                'cargo_institucional_id' => $user->cargo_institucional_id,
                'programa_estudio_id' => $student instanceof PerfilEstudiante ? $student->programa_estudio_id : $teacher?->programa_estudio_id,
                'condicion_academica' => $student?->condicion_academica,
                'ciclo_actual' => $student?->ciclo_actual,
                'anio_egreso' => $student?->anio_egreso,
                'direccion_residencia' => $student?->direccion_residencia,
                'especialidad' => $teacher?->especialidad,
                'condicion_laboral' => $teacher?->condicion_laboral,
                'teacher_request' => $teacherRequest ? [
                    'cargo' => $teacherRequest->cargo,
                    'motivo' => $teacherRequest->motivo,
                    'created_at' => $teacherRequest->created_at,
                ] : null,
            ],
            'programas' => ProgramaEstudio::query()->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'cargos' => DB::table('cargos_institucionales')
                ->where(fn ($query) => $query->where('activo', true)->orWhere('id', $user->cargo_institucional_id ?? 0))
                ->orderBy('nombre')->get(['id', 'nombre']),
            'passwordRules' => null,
            'viewerId' => (int) $request->user()->id,
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
                'cargo_institucional_id' => $data['rol'] === 'estudiante' ? null : (array_key_exists('cargo_institucional_id', $data)
                    ? $data['cargo_institucional_id'] : $current->cargo_institucional_id),
                'sesion_version' => $current->sesion_version + (int) $roleChanged,
                'remember_token' => $roleChanged ? Str::random(60) : $current->remember_token,
            ]);

            if ($changed !== 1) {
                throw ValidationException::withMessages(['rol' => 'No se pudo cambiar el rol; verifique asignaciones y administradores activos.']);
            }

            $current->refresh();
            $this->syncProfile($current, $data);
            app(LinkApplicantAccount::class)->execute($current, $actor);
            $this->recordEvent($current, $actor, 'edit', $current->estado_cuenta, $current->estado_cuenta);

            if ($roleChanged) {
                $this->recordEvent($current, $actor, 'change_role', $current->estado_cuenta, $current->estado_cuenta,
                    'Rol '.$user->rol.' → '.$data['rol']);
                DB::table('sessions')->where('user_id', $current->id)->delete();
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Cambios realizados. Los datos del usuario se actualizaron.']);

        return to_route('admin.users.edit', ['user' => $user->id]);
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
            if ($current->rol === 'asistente' && $action === 'activate') {
                throw ValidationException::withMessages(['accion' => 'El rol Asistente fue retirado. Edite la cuenta y asígnele un rol vigente antes de activarla.']);
            }
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

            if ($newState === 'activo') {
                $current->refresh();
                app(LinkApplicantAccount::class)->execute($current, $actor);
            }

            $accountEventId = DB::table('user_account_events')->insertGetId([
                'user_id' => $current->id,
                'actor_id' => $actor->id,
                'accion' => $action,
                'estado_anterior' => $current->estado_cuenta,
                'estado_nuevo' => $newState,
                'motivo' => $input['motivo'] ?? null,
                'created_at' => now(),
            ]);
            if (in_array($action, ['activate', 'reject'], true)) {
                $approved = $action === 'activate';
                DB::table('tramite_notificaciones')->insertOrIgnore([
                    'usuario_id' => $current->id,
                    'account_event_id' => $accountEventId,
                    'tipo' => $approved ? 'cuenta_aprobada' : 'cuenta_rechazada',
                    'titulo' => $approved ? 'Cuenta aprobada' : 'Cuenta rechazada',
                    'mensaje' => $approved
                        ? 'Su cuenta fue aprobada y está habilitada.'
                        : 'Su solicitud de cuenta fue rechazada.',
                    'prioridad' => 'normal',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            DB::table('sessions')->where('user_id', $current->id)->delete();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Cambios realizados. El estado de la cuenta se actualizó.']);

        return to_route('admin.users.edit', ['user' => $user->id]);
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        DB::transaction(function () use ($actor, $user): void {
            $current = User::query()->findOrFail($user->id);

            if ($current->id === $actor->id) {
                throw ValidationException::withMessages(['delete' => 'No puedes eliminar tu propia cuenta.']);
            }

            if ($current->rol === 'administrador' && $current->activo && $current->estado_cuenta === 'activo') {
                $activeAdmins = User::query()->where('rol', 'administrador')->where('activo', true)->where('estado_cuenta', 'activo')->count();

                if ($activeAdmins <= 1) {
                    throw ValidationException::withMessages(['delete' => 'No puedes eliminar al último administrador activo.']);
                }
            }

            $hasWorkflowHistory = DB::table('tramites')->where(fn ($query) => $query
                ->where('propietario_id', $current->id)
                ->orWhere('destino_docente_id', $current->id))->exists()
                || DB::table('tramite_documentos')->where('cargado_por', $current->id)->exists()
                || DB::table('tramite_eventos')->where('usuario_id', $current->id)->exists()
                || DB::table('tramite_borradores')->where(fn ($query) => $query
                    ->where('remitente_id', $current->id)
                    ->orWhere('firmante_id', $current->id)
                    ->orWhere('creado_por', $current->id))->exists()
                || DB::table('tramite_documentos_finales')->where(fn ($query) => $query
                    ->where('reservada_por', $current->id)
                    ->orWhere('generado_por', $current->id)
                    ->orWhere('anulado_por', $current->id))->exists()
                || DB::table('tramite_firmas')->where(fn ($query) => $query
                    ->where('firmante_id', $current->id)
                    ->orWhere('registrado_por', $current->id))->exists()
                || DB::table('tramite_entregas')->where(fn ($query) => $query
                    ->where('entregado_por', $current->id)
                    ->orWhere('receptor_usuario_id', $current->id))->exists()
                || DB::table('tramite_evidencias_entrega')->where('registrado_por', $current->id)->exists()
                || DB::table('tramite_cierres')->where('cerrado_por', $current->id)->exists()
                || DB::table('tramite_informes_cierre')->where('generado_por', $current->id)->exists()
                || DB::table('tramite_asignaciones')->where('revisor_id', $current->id)->exists()
                || DB::table('tramite_rondas_revision')->where('revisor_id', $current->id)->exists()
                || DB::table('tramite_observaciones_revision')->where('revisor_id', $current->id)->exists()
                || DB::table('tramite_respuestas_observacion')->where('asistente_id', $current->id)->exists()
                || DB::table('tramite_borrador_valores')->where('usuario_id', $current->id)->exists()
                || DB::table('tramite_notificaciones')->where('usuario_id', $current->id)->whereNotNull('tramite_id')->exists()
                || DB::table('tramite_notificacion_eventos')->where('actor_id', $current->id)->exists();

            if ($hasWorkflowHistory) {
                throw ValidationException::withMessages([
                    'delete' => 'Esta cuenta tiene trámites o registros vinculados. Desactívala para conservar el historial.',
                ]);
            }

            DB::table('user_account_events')->insert([
                'user_id' => $current->id,
                'actor_id' => $actor->id,
                'accion' => 'delete',
                'estado_anterior' => $current->estado_cuenta,
                'estado_nuevo' => 'eliminado',
                'motivo' => 'Cuenta eliminada permanentemente.',
                'created_at' => now(),
            ]);

            DB::table('tramite_notificaciones')->where('usuario_id', $current->id)->whereNull('tramite_id')->delete();
            DB::table('sessions')->where('user_id', $current->id)->delete();
            DB::table('password_reset_tokens')->where('email', $current->email)->delete();
            $current->delete();
        });

        Storage::disk('local')->delete('firmas-perfil/'.$user->id.'.jpg');
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Usuario eliminado correctamente.']);

        return to_route('admin.users.index');
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

        return to_route('admin.users.edit', ['user' => $user->id]);
    }

    /** @param array<string, mixed> $data */
    private function syncProfile(User $user, array $data): void
    {
        if ($data['rol'] === 'estudiante') {
            PerfilEstudiante::query()->updateOrCreate(['user_id' => $user->id], [
                'codigo_estudiante' => $data['dni'],
                'programa_estudio_id' => $data['programa_estudio_id'],
                'condicion_academica' => $data['condicion_academica'],
                'ciclo_actual' => $data['condicion_academica'] === 'Estudiante' ? $data['ciclo_actual'] : null,
                'anio_egreso' => $data['condicion_academica'] === 'Egresado' ? $data['anio_egreso'] : null,
                'direccion_residencia' => $data['direccion_residencia'] ?? null,
            ]);
            PerfilDocente::query()->where('user_id', $user->id)->delete();
        } elseif ($data['rol'] === 'docente') {
            PerfilDocente::query()->updateOrCreate(['user_id' => $user->id], [
                'codigo_docente' => $data['dni'],
                'programa_estudio_id' => $data['programa_estudio_id'] ?? null,
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
