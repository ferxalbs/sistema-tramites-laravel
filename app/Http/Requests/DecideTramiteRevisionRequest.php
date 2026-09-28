<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DecideTramiteRevisionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->activo && in_array($user->rol, ['docente', 'administrador'], true);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', 'string', Rule::in(['aprobar', 'rechazar'])],
            'conclusion' => ['required', 'string', 'min:8', 'max:4000'],
            'comentario_publico' => ['nullable', 'string', 'required_if:decision,rechazar', 'min:8', 'max:4000'],
            'comentario_interno' => ['nullable', 'string', 'max:4000'],
        ];
    }
}
