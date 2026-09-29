<?php

namespace App\Http\Requests\Settings;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User
            && $this->user()->activo
            && in_array($this->user()->rol, ['estudiante', 'docente'], true);
    }

    protected function prepareForValidation(): void
    {
        $normalized = ['celular' => $this->normalizedText('celular')];

        if ($this->user()?->rol === 'estudiante') {
            $alternativeEmail = $this->normalizedText('correo_alternativo');
            $normalized['correo_alternativo'] = is_string($alternativeEmail) ? mb_strtolower($alternativeEmail) : $alternativeEmail;
            $normalized['direccion_residencia'] = $this->normalizedText('direccion_residencia');
        } elseif ($this->user()?->rol === 'docente') {
            $normalized['especialidad'] = $this->normalizedText('especialidad');
            $normalized['condicion_laboral'] = $this->normalizedText('condicion_laboral');
        }

        $this->merge($normalized);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'celular' => ['required', 'string', 'regex:/^[0-9+() -]{7,20}$/'],
        ];

        if ($this->user()?->rol === 'estudiante') {
            $rules['correo_alternativo'] = [
                'nullable', 'email', 'max:190',
                Rule::notIn([$this->user()->email]),
                Rule::unique('users', 'email')->ignore($this->user()->id),
                Rule::unique('users', 'correo_alternativo')->ignore($this->user()->id),
            ];
            $rules['direccion_residencia'] = ['nullable', 'string', 'max:255'];
        } elseif ($this->user()?->rol === 'docente') {
            $rules['especialidad'] = ['nullable', 'string', 'max:160'];
            $rules['condicion_laboral'] = ['nullable', 'string', 'max:100'];
        }

        return $rules;
    }

    private function normalizedText(string $field): mixed
    {
        $value = $this->input($field);

        return is_string($value) ? trim($value) : $value;
    }
}
