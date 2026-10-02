<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReassignTramiteAsignacionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->activo && $user->rol === 'administrador';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'destino' => ['required', 'string', Rule::in(['docente', 'oficina'])],
            'revisor_id' => ['required', 'integer', 'exists:users,id'],
            'motivo' => ['nullable', 'string', 'max:255'],
            'instrucciones_revision' => ['nullable', 'string', 'max:2000'],
            'fecha_esperada' => ['nullable', 'date_format:Y-m-d'],
            'motivo_reasignacion' => ['required', 'string', 'min:8', 'max:1000'],
        ];
    }
}
