<?php

namespace App\Http\Requests;

use App\Models\TramiteObservacionRevision;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTramiteRevisionObservationRequest extends FormRequest
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
            'resumen' => ['required', 'string', 'min:8', 'max:2000'],
            'observaciones' => ['required', 'array', 'min:1', 'max:20'],
            'observaciones.*' => ['required', 'array:categoria,titulo,descripcion,seccion,obligatoria,visible_para_interesado'],
            'observaciones.*.categoria' => ['required', 'string', Rule::in(TramiteObservacionRevision::CATEGORIAS)],
            'observaciones.*.titulo' => ['required', 'string', 'min:3', 'max:160'],
            'observaciones.*.descripcion' => ['required', 'string', 'min:5', 'max:5000'],
            'observaciones.*.seccion' => ['nullable', 'string', 'max:160'],
            'observaciones.*.obligatoria' => ['required', 'boolean'],
            'observaciones.*.visible_para_interesado' => ['required', 'boolean'],
        ];
    }
}
