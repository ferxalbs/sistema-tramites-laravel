<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreTramiteBorradorRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->activo
            && $user->rol === 'administrador';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'plantilla_id' => ['required', 'integer', 'exists:tramite_plantillas,id'],
            'remitente_id' => ['nullable', 'integer', 'exists:users,id', 'required_if:preparar,1'],
            'firmante_id' => ['nullable', 'integer', 'exists:users,id', 'required_if:preparar,1'],
            'fecha_documento' => ['required', 'date_format:Y-m-d'],
            'lugar' => ['required', 'string', 'max:80'],
            'asunto' => ['required', 'string', 'max:255'],
            'introduccion' => ['nullable', 'string', 'max:5000'],
            'contenido_principal' => ['nullable', 'string', 'max:20000', 'required_if:preparar,1'],
            'cierre' => ['nullable', 'string', 'max:5000'],
            'destinatarios' => ['nullable', 'array', 'max:20', 'required_if:preparar,1'],
            'destinatarios.*.nombres' => ['required', 'string', 'max:120'],
            'destinatarios.*.apellidos' => ['nullable', 'string', 'max:120'],
            'destinatarios.*.cargo' => ['nullable', 'string', 'max:160'],
            'destinatarios.*.correo' => ['nullable', 'email', 'max:190'],
            'destinatarios.*.principal' => ['nullable', 'boolean'],
            'personas_mencionadas' => ['nullable', 'array', 'max:20'],
            'personas_mencionadas.*.nombres' => ['required', 'string', 'max:120'],
            'personas_mencionadas.*.apellidos' => ['nullable', 'string', 'max:120'],
            'personas_mencionadas.*.cargo' => ['nullable', 'string', 'max:160'],
            'personas_mencionadas.*.dni' => ['nullable', 'digits:8'],
            'adjuntos' => ['nullable', 'array', 'max:50'],
            'adjuntos.*' => ['integer', 'distinct'],
            'campos' => ['nullable', 'array', 'max:26'],
            'campos.*' => ['nullable', 'string', 'max:60000'],
            'preparar' => ['required', 'boolean'],
            'confirmar_fecha_anterior' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'plantilla_id' => 'plantilla',
            'remitente_id' => 'remitente',
            'firmante_id' => 'firmante propuesto',
            'fecha_documento' => 'fecha del documento',
            'lugar' => 'lugar',
            'asunto' => 'asunto',
            'contenido_principal' => 'contenido principal',
            'destinatarios.*.nombres' => 'nombre del destinatario',
            'destinatarios.*.correo' => 'correo del destinatario',
        ];
    }
}
