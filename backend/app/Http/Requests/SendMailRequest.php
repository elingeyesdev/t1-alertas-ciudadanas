<?php

namespace App\Http\Requests;

class SendMailRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'subject' => ['required', 'string'],
            'content' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return parent::messages() + [
            'subject.required' => 'El asunto no puede estar vacío.',
            'subject.string' => 'El asunto debe ser una cadena de texto.',
            'content.required' => 'El contenido no puede estar vacío.',
            'content.string' => 'El contenido debe ser una cadena de texto.',
        ];
    }
}
