<?php

namespace App\Http\Requests;

use App\Models\User;

class AssistantStudentAccountRequest extends AdminUserRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user');

        return $this->user()?->rol === 'asistente'
            && ($target === null || ($target instanceof User && $target->rol === 'estudiante'));
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();
        $this->merge(['rol' => 'estudiante']);
    }
}
