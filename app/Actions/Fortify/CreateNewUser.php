<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Models\PerfilEstudiante;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $input['nombres'] = trim($input['nombres'] ?? '');
        $input['apellidos'] = trim($input['apellidos'] ?? '');
        $input['dni'] = preg_replace('/\s+/', '', $input['dni'] ?? '') ?? '';
        $input['email'] = mb_strtolower(trim($input['email'] ?? ''));
        $input['correo_alternativo'] = mb_strtolower(trim($input['correo_alternativo'] ?? ''));
        $input['celular'] = trim($input['celular'] ?? '');

        $personName = static function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value) || preg_match("/^[\\p{L}\\p{M} .'-]+$/u", $value) !== 1) {
                $fail('Ingrese nombres y apellidos válidos.');
            }
        };

        $data = Validator::make($input, [
            'nombres' => ['required', 'string', 'min:2', 'max:120', $personName],
            'apellidos' => ['required', 'string', 'min:2', 'max:120', $personName],
            'dni' => ['required', 'digits:8', Rule::unique('users', 'dni')],
            'celular' => ['required', 'regex:/^[0-9+() -]{7,20}$/'],
            'email' => [
                'required', 'email', 'max:190',
                'regex:/\Aa\.[a-z0-9._-]+@seoane\.edu\.pe\z/D',
                Rule::unique('users', 'email'),
                Rule::unique('users', 'correo_alternativo'),
            ],
            'correo_alternativo' => [
                'nullable', 'email', 'max:190', 'different:email',
                Rule::unique('users', 'email'),
                Rule::unique('users', 'correo_alternativo'),
            ],
            'programa_estudio_id' => ['required', 'integer', Rule::exists('programas_estudio', 'id')->where('activo', true)],
            'condicion_academica' => ['required', Rule::in(['Estudiante', 'Egresado'])],
            'ciclo_actual' => [Rule::requiredIf(($input['condicion_academica'] ?? '') === 'Estudiante'), 'nullable', 'integer', 'between:1,6'],
            'anio_egreso' => [Rule::requiredIf(($input['condicion_academica'] ?? '') === 'Egresado'), 'nullable', 'integer', 'between:1950,'.now()->year],
            'direccion_residencia' => ['nullable', 'string', 'max:255'],
            'acepta_terminos' => ['accepted'],
            'password' => $this->passwordRules(),
        ])->validate();

        return DB::transaction(function () use ($data): User {
            $user = User::create([
                'name' => $data['nombres'].' '.$data['apellidos'],
                'nombres' => $data['nombres'],
                'apellidos' => $data['apellidos'],
                'dni' => $data['dni'],
                'celular' => $data['celular'],
                'email' => $data['email'],
                'correo_alternativo' => $data['correo_alternativo'] ?? null,
                'password' => $data['password'],
                'rol' => 'estudiante',
                'activo' => false,
                'estado_cuenta' => 'pendiente',
                'cuenta_provisional' => false,
            ]);

            PerfilEstudiante::create([
                'user_id' => $user->id,
                'programa_estudio_id' => $data['programa_estudio_id'],
                'condicion_academica' => $data['condicion_academica'],
                'ciclo_actual' => $data['condicion_academica'] === 'Estudiante' ? $data['ciclo_actual'] : null,
                'anio_egreso' => $data['condicion_academica'] === 'Egresado' ? $data['anio_egreso'] : null,
                'direccion_residencia' => $data['direccion_residencia'] ?? null,
            ]);

            return $user;
        });
    }
}
