<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssistantStudentAccountRequest;
use App\Models\PerfilEstudiante;
use App\Models\ProgramaEstudio;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class AssistantStudentAccountController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
            'programa' => ['nullable', 'integer', 'min:0'],
            'condicion' => ['nullable', Rule::in(['all', 'Estudiante', 'Egresado'])],
            'estado' => ['nullable', Rule::in(['all', 'activo', 'pendiente', 'inactivo', 'rechazado'])],
        ]);
        $search = trim($filters['q'] ?? '');
        $students = User::query()->where('rol', 'estudiante')->with('perfilEstudiante.programa');

        if ($search !== '') {
            $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
            $students->where(fn (Builder $users): Builder => $users
                ->whereRaw("name LIKE ? ESCAPE '!'", [$like])
                ->orWhereRaw("dni LIKE ? ESCAPE '!'", [$like])
                ->orWhereRaw("email LIKE ? ESCAPE '!'", [$like])
                ->orWhereHas('perfilEstudiante', fn (Builder $profile): Builder => $profile
                    ->whereRaw("codigo_estudiante LIKE ? ESCAPE '!'", [$like])));
        }

        $students->when(isset($filters['estado']) && $filters['estado'] !== 'all', fn (Builder $users): Builder => $users->where('estado_cuenta', $filters['estado']))
            ->when(isset($filters['programa']) && (int) $filters['programa'] > 0, fn (Builder $users): Builder => $users->whereHas('perfilEstudiante', fn (Builder $profile): Builder => $profile->where('programa_estudio_id', $filters['programa'])))
            ->when(isset($filters['condicion']) && $filters['condicion'] !== 'all', fn (Builder $users): Builder => $users->whereHas('perfilEstudiante', fn (Builder $profile): Builder => $profile->where('condicion_academica', $filters['condicion'])));

        return Inertia::render('estudiantes', [
            'students' => $students->orderByDesc('created_at')->orderByDesc('id')->paginate(10)
                ->through(fn (User $user): array => [
                    'id' => $user->id,
                    'nombre' => $user->name,
                    'email' => $user->email,
                    'dni' => $user->dni === null ? null : str_repeat('•', max(0, mb_strlen($user->dni) - 2)).mb_substr($user->dni, -2),
                    'codigo' => $user->perfilEstudiante?->codigo_estudiante,
                    'programa' => $user->perfilEstudiante?->programa?->nombre,
                    'condicion' => $user->perfilEstudiante?->condicion_academica,
                    'estado' => $user->estado_cuenta,
                ])->withQueryString(),
            'filters' => [
                'q' => $search,
                'programa' => (int) ($filters['programa'] ?? 0),
                'condicion' => $filters['condicion'] ?? 'all',
                'estado' => $filters['estado'] ?? 'all',
            ],
            'programas' => ProgramaEstudio::query()->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function create(): InertiaResponse
    {
        return Inertia::render('usuarios-form', [
            'mode' => 'assistant',
            'user' => null,
            'programas' => ProgramaEstudio::query()->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function store(AssistantStudentAccountRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        $student = DB::transaction(function () use ($data, $actor): User {
            $student = User::query()->create([
                'name' => $data['nombres'].' '.$data['apellidos'],
                'nombres' => $data['nombres'],
                'apellidos' => $data['apellidos'],
                'dni' => $data['dni'],
                'celular' => $data['celular'],
                'email' => $data['email'],
                'correo_alternativo' => $data['correo_alternativo'] ?? null,
                'password' => $data['password'],
                'rol' => 'estudiante',
                'activo' => true,
                'estado_cuenta' => 'activo',
                'debe_cambiar_password' => true,
                'cuenta_provisional' => true,
            ]);
            $student->forceFill(['email_verified_at' => now()])->save();
            $this->saveProfile($student, $data);
            $this->recordEvent($student, $actor, 'create', 'nuevo', $student->estado_cuenta);

            return $student;
        });

        return to_route('assistant.students.edit', $student)
            ->with('success', 'Cuenta de estudiante creada con contraseña temporal.');
    }

    public function edit(User $user): InertiaResponse
    {
        abort_unless($user->rol === 'estudiante', 404);
        $user->load('perfilEstudiante');
        $profile = $user->perfilEstudiante;

        return Inertia::render('usuarios-form', [
            'mode' => 'assistant',
            'user' => [
                'id' => $user->id,
                'rol' => 'estudiante',
                'nombres' => $user->nombres,
                'apellidos' => $user->apellidos,
                'dni' => $user->dni,
                'celular' => $user->celular,
                'email' => $user->email,
                'correo_alternativo' => $user->correo_alternativo,
                'codigo_estudiante' => $profile?->codigo_estudiante,
                'codigo_docente' => null,
                'programa_estudio_id' => $profile?->programa_estudio_id,
                'condicion_academica' => $profile?->condicion_academica,
                'ciclo_actual' => $profile?->ciclo_actual,
                'anio_egreso' => $profile?->anio_egreso,
                'direccion_residencia' => $profile?->direccion_residencia,
                'especialidad' => null,
                'condicion_laboral' => null,
            ],
            'programas' => ProgramaEstudio::query()->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'passwordRules' => null,
        ]);
    }

    public function save(AssistantStudentAccountRequest $request, User $user): RedirectResponse
    {
        abort_unless($user->rol === 'estudiante', 404);
        $data = $request->validated();
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        DB::transaction(function () use ($user, $data, $actor): void {
            $updated = User::query()->whereKey($user->id)->where('rol', 'estudiante')->update([
                'name' => $data['nombres'].' '.$data['apellidos'],
                'nombres' => $data['nombres'],
                'apellidos' => $data['apellidos'],
                'dni' => $data['dni'],
                'celular' => $data['celular'],
                'email' => $data['email'],
                'correo_alternativo' => $data['correo_alternativo'] ?? null,
            ]);
            abort_unless($updated === 1, 409);
            $this->saveProfile($user, $data);
            $this->recordEvent($user, $actor, 'edit', $user->estado_cuenta, $user->estado_cuenta);
        });

        return to_route('assistant.students.index')->with('success', 'Perfil de estudiante actualizado.');
    }

    /** @param array<string, mixed> $data */
    private function saveProfile(User $student, array $data): void
    {
        PerfilEstudiante::query()->updateOrCreate(['user_id' => $student->id], [
            'codigo_estudiante' => $data['codigo_estudiante'],
            'programa_estudio_id' => $data['programa_estudio_id'],
            'condicion_academica' => $data['condicion_academica'],
            'ciclo_actual' => $data['condicion_academica'] === 'Estudiante' ? ($data['ciclo_actual'] ?? null) : null,
            'anio_egreso' => $data['condicion_academica'] === 'Egresado' ? ($data['anio_egreso'] ?? null) : null,
            'direccion_residencia' => $data['direccion_residencia'] ?? null,
        ]);
    }

    private function recordEvent(User $student, User $actor, string $action, string $before, string $after): void
    {
        DB::table('user_account_events')->insert([
            'user_id' => $student->id,
            'actor_id' => $actor->id,
            'accion' => $action,
            'estado_anterior' => $before,
            'estado_nuevo' => $after,
            'motivo' => null,
            'created_at' => now(),
        ]);
    }
}
