<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;

class CorrectTramiteDraftRequest extends StoreTramiteBorradorRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->activo && $user->rol === 'asistente';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'resumen_correccion' => ['required', 'string', 'min:8', 'max:2000'],
            'respuestas' => ['nullable', 'array', 'max:20'],
            'respuestas.*' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
