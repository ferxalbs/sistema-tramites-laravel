<?php

namespace App\Http\Requests;

use App\Concerns\PasswordValidationRules;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminUserRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->rol === 'administrador';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $target = $this->route('user');
        $targetId = $target instanceof User ? $target->id : null;
        $role = $this->input('rol');
        $isCreate = $targetId === null;
        $studentProfileId = $target instanceof User ? $target->perfilEstudiante?->id : null;
        $teacherProfileId = $target instanceof User ? $target->perfilDocente?->id : null;

        $rules = [
            'rol' => ['required', Rule::in(['estudiante', 'asistente', 'docente', 'administrador'])],
            'nombres' => ['required', 'string', 'min:2', 'max:120', 'regex:/^[\p{L}\p{M} .\'-]+$/u'],
            'apellidos' => ['required', 'string', 'min:2', 'max:120', 'regex:/^[\p{L}\p{M} .\'-]+$/u'],
            'dni' => ['required', 'digits:8', Rule::unique('users', 'dni')->ignore($targetId)],
            'celular' => ['required', 'regex:/^[0-9+() -]{7,20}$/'],
            'email' => [
                'required', 'email', 'max:190',
                $role === 'estudiante'
                    ? 'regex:/\Aa\.[a-z0-9._-]+@seoane\.edu\.pe\z/D'
                    : 'regex:/@seoane\.edu\.pe\z/D',
                Rule::unique('users', 'email')->ignore($targetId),
                Rule::unique('users', 'correo_alternativo')->ignore($targetId),
            ],
            'correo_alternativo' => [
                'nullable', 'email', 'max:190', 'different:email',
                Rule::unique('users', 'email')->ignore($targetId),
                Rule::unique('users', 'correo_alternativo')->ignore($targetId),
            ],
            'confirmar_administrador' => [
                Rule::requiredIf($role === 'administrador' && ($isCreate || $target->rol !== 'administrador')),
                'nullable', 'accepted',
            ],
        ];

        if ($isCreate) {
            $rules['password'] = $this->passwordRules();
            $rules['activar_inmediatamente'] = ['sometimes', 'boolean'];
        }

        if ($role === 'estudiante') {
            $rules += [
                'codigo_estudiante' => [
                    'required', 'regex:/^[A-Z0-9._-]{3,40}$/',
                    Rule::unique('perfiles_estudiante', 'codigo_estudiante')->ignore($studentProfileId),
                ],
                'programa_estudio_id' => ['required', 'integer', Rule::exists('programas_estudio', 'id')->where('activo', true)],
                'condicion_academica' => ['required', Rule::in(['Estudiante', 'Egresado'])],
                'ciclo_actual' => [Rule::requiredIf($this->input('condicion_academica') === 'Estudiante'), 'nullable', 'integer', 'between:1,10'],
                'anio_egreso' => [Rule::requiredIf($this->input('condicion_academica') === 'Egresado'), 'nullable', 'integer', 'between:1950,'.now()->year],
                'direccion_residencia' => ['nullable', 'string', 'max:255'],
            ];
        } elseif ($role === 'docente') {
            $rules += [
                'programa_estudio_id' => ['required', 'integer', Rule::exists('programas_estudio', 'id')->where('activo', true)],
                'codigo_docente' => [
                    'nullable', 'regex:/^[A-Z0-9._-]{3,40}$/',
                    Rule::unique('perfiles_docente', 'codigo_docente')->ignore($teacherProfileId),
                ],
                'especialidad' => ['nullable', 'string', 'max:160'],
                'condicion_laboral' => ['nullable', 'string', 'max:100'],
            ];
        }

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (['nombres', 'apellidos', 'celular', 'direccion_residencia', 'especialidad', 'condicion_laboral'] as $field) {
            if (is_string($this->input($field))) {
                $normalized[$field] = trim($this->input($field));
            }
        }

        foreach (['email', 'correo_alternativo'] as $field) {
            if (is_string($this->input($field))) {
                $normalized[$field] = mb_strtolower(trim($this->input($field)));
            }
        }

        foreach (['codigo_estudiante', 'codigo_docente'] as $field) {
            if (is_string($this->input($field))) {
                $normalized[$field] = mb_strtoupper(trim($this->input($field)));
            }
        }

        if (is_string($this->input('dni'))) {
            $normalized['dni'] = preg_replace('/\s+/', '', $this->input('dni'));
        }

        $this->merge($normalized);
    }
}
