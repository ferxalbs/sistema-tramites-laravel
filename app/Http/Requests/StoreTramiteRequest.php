<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTramiteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'clasificacion' => ['required', 'string', Rule::in(array_keys(config('tramites.clasificaciones')))],
            'tipo_documento' => [
                'required',
                'string',
                Rule::in(array_keys(config('tramites.tipos_documento.'.$this->input('clasificacion'), []))),
            ],
            'persona_nombre' => ['required', 'string', 'max:200'],
            'persona_identificador' => ['nullable', 'string', 'max:50'],
            'propietario_id' => [
                'nullable',
                'required_if:clasificacion,estudiantil',
                'integer',
                Rule::exists('users', 'id')->where(fn (Builder $query) => $query->where('rol', 'estudiante')->where('activo', true)),
            ],
            'destino_tipo' => ['required', 'string', Rule::in(array_keys(config('tramites.destinos')))],
            'destino_nombre' => ['required', 'string', 'max:200'],
            'asunto' => ['required', 'string', 'max:200'],
            'descripcion' => ['nullable', 'string', 'max:10000'],
            'prioridad' => ['required', 'string', Rule::in(array_keys(config('tramites.prioridades')))],
            'fecha_recepcion' => ['required', 'date'],
            'folios' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'documento' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'clasificacion' => 'clasificación',
            'tipo_documento' => 'tipo de documento',
            'persona_nombre' => 'nombre de la persona solicitante',
            'persona_identificador' => 'documento de identidad o código',
            'propietario_id' => 'estudiante o egresado relacionado',
            'destino_tipo' => 'tipo de destino',
            'destino_nombre' => 'destino',
            'asunto' => 'asunto',
            'descripcion' => 'descripción',
            'prioridad' => 'prioridad',
            'fecha_recepcion' => 'fecha de recepción',
            'folios' => 'número de folios',
            'documento' => 'documento digitalizado',
        ];
    }
}
