<?php

namespace App\Http\Requests;

use App\Rules\SafeReceptionDocument;
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
            'programa_estudio_id' => [
                'nullable',
                'integer',
                Rule::exists('programas_estudio', 'id')->where(fn (Builder $query) => $query->where('activo', true)),
            ],
            'destino_tipo' => ['required', 'string', Rule::in(array_keys(config('tramites.destinos')))],
            'destino_nombre' => ['required', 'string', 'max:200'],
            'asunto' => ['required', 'string', 'min:3', 'max:255'],
            'descripcion' => ['required', 'string', 'min:3', 'max:5000'],
            'prioridad' => ['required', 'string', Rule::in(array_keys(config('tramites.prioridades')))],
            'fecha_llegada_oficina' => ['required', 'date_format:Y-m-d\\TH:i'],
            'fecha_presentacion_original' => ['nullable', 'date_format:Y-m-d'],
            'numero_expediente_externo' => ['nullable', 'string', 'max:80', 'regex:/^[\\p{L}\\p{N}\\s.\\-\\/#]+$/u'],
            'area_procedencia' => ['nullable', 'string', 'max:160'],
            'persona_entrega_documento' => ['nullable', 'string', 'max:180'],
            'observacion_recepcion' => ['nullable', 'string', 'max:2000'],
            'folios' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'confirmar_recepcion' => ['required', 'accepted'],
            'documentos' => ['sometimes', 'array'],
            'documentos.*' => ['required', 'array:categoria,archivo'],
            'documentos.*.categoria' => ['required', 'string', Rule::in(['documento_original', 'documento_escaneado'])],
            'documentos.*.archivo' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'extensions:pdf,jpg,jpeg,png', 'max:10240', new SafeReceptionDocument],
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
            'programa_estudio_id' => 'programa de estudios',
            'destino_tipo' => 'tipo de destino',
            'destino_nombre' => 'destino',
            'asunto' => 'asunto',
            'descripcion' => 'descripción',
            'prioridad' => 'prioridad',
            'fecha_llegada_oficina' => 'fecha y hora de llegada a oficina',
            'fecha_presentacion_original' => 'fecha de presentación original',
            'numero_expediente_externo' => 'referencia física externa',
            'area_procedencia' => 'área de procedencia',
            'persona_entrega_documento' => 'persona que entrega el documento',
            'observacion_recepcion' => 'observación de recepción',
            'folios' => 'número de folios',
            'confirmar_recepcion' => 'confirmación de la recepción física',
            'documentos.*.categoria' => 'categoría del documento',
            'documentos.*.archivo' => 'archivo recibido',
        ];
    }
}
