<?php

namespace App\Http\Requests;

class UpdatePasswordRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:6', 'max:20', 'regex:/^\S+$/'],
        ];
    }

    public function messages(): array
    {
        return parent::messages() + [
            'new_password.min' => 'La contraseña debe tener al menos 6 caracteres',
            'new_password.max' => 'La contraseña no debe exceder los 20 caracteres',
            'new_password.regex' => 'La contraseña no debe contener espacios',
        ];
    }
}
