<?php

namespace App\Http\Requests;

class UpdateTramiteRequest extends StoreTramiteRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['documentos'], $rules['documentos.*'], $rules['documentos.*.categoria'], $rules['documentos.*.archivo']);
        $rules['documentos'] = ['prohibited'];
        $rules['confirmar_recepcion'] = ['prohibited'];

        return $rules;
    }
}
