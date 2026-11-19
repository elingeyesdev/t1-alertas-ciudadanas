<?php

namespace App\Http\Requests;

/** HU-25: contacto del círculo de confianza agregado a mano (sin la app). */
class TrustContactRequest extends ApiRequest
{
    protected function prepareForValidation(): void
    {
        $this->clean(['name', 'phone']);
    }

    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'min:2', 'max:80'],
            'phone' => [$required, 'string', 'regex:/^\+?[0-9 ()-]{7,20}$/'],
        ];
    }

    public function messages(): array
    {
        return parent::messages() + [
            'name.required' => 'Escribe el nombre del contacto.',
            'phone.required' => 'Escribe el teléfono del contacto.',
            'phone.regex' => 'El teléfono no es válido.',
        ];
    }
}
