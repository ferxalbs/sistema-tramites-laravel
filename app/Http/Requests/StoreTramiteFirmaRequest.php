<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreTramiteFirmaRequest extends FormRequest
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
            'no_requiere_firma' => ['nullable', 'boolean'],
            'fecha_firma' => ['required_unless:no_requiere_firma,1', 'date_format:Y-m-d\\TH:i'],
            'observacion' => ['nullable', 'string', 'max:1000'],
            'evidencia' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }
}
