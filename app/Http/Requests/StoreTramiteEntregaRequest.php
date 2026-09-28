<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTramiteEntregaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->activo && in_array($user->rol, ['asistente', 'administrador'], true);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'medio_entrega_id' => ['required', 'integer', Rule::exists('tramite_medios_entrega', 'id')->where('activo', true)],
            'receptor_nombre' => ['required', 'string', 'min:3', 'max:160'],
            'receptor_documento' => ['nullable', 'string', 'max:30'],
            'receptor_tipo' => ['required', 'string', Rule::in([
                'Estudiante',
                'Egresado',
                'Docente',
                'Autoridad',
                'Representante autorizado',
                'Otro',
            ])],
            'receptor_relacion' => ['nullable', 'string', 'max:160'],
            'correo_destino' => ['nullable', 'email', 'max:255'],
            'medio_utilizado' => ['nullable', 'string', 'max:255'],
            'fecha_entrega' => ['required', 'date_format:Y-m-d\\TH:i'],
            'tipo_evidencia' => ['nullable', 'string', Rule::in([
                'Constancia firmada',
                'Fotografía del documento',
                'Archivo PDF',
                'Imagen',
                'Código de confirmación',
                'Confirmación manual',
            ])],
            'evidencia' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'observacion' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
