<?php

namespace App\Http\Requests;

/** Equivale a CreateUserRequest */
class RegisterUserRequest extends ApiRequest
{
    protected function prepareForValidation(): void
    {
        $this->clean(['first_name', 'last_name'], true);
        $this->clean(['phone']);
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'digits:8'],
            'password' => ['required', 'string', 'min:6', 'max:20', 'regex:/^\S+$/'],
            'roleId' => ['required', 'integer'],
        ];
    }

    public function messages(): array
    {
        return parent::messages() + [
            'first_name.string' => 'El nombre de usuario debe ser una cadena de texto.',
            'last_name.string' => 'El apellido de usuario debe ser una cadena de texto.',
            'phone.digits' => 'El teléfono debe tener exactamente 8 dígitos.',
            'password.min' => 'La contraseña debe tener al menos 6 caracteres',
            'password.max' => 'La contraseña no debe exceder los 20 caracteres',
            'password.regex' => 'La contraseña no debe contener espacios',
        ];
    }
}
