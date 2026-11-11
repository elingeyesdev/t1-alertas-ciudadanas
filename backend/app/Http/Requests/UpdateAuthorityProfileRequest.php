<?php

namespace App\Http\Requests;

use App\Enums\ProfileType;
use Illuminate\Validation\Rule;

class UpdateAuthorityProfileRequest extends ApiRequest
{
    protected function prepareForValidation(): void
    {
        $this->clean(['ci']);
        $this->clean(['gmail'], true);
    }

    public function rules(): array
    {
        return [
            'ci' => ['sometimes', 'string', 'max:255'],
            'gmail' => ['sometimes', 'email', 'max:255'],
            'profile_type' => ['sometimes', Rule::enum(ProfileType::class)],
        ];
    }

    public function messages(): array
    {
        return parent::messages() + [
            'ci.string' => 'El CI debe ser una cadena de texto.',
            'profile_type.enum' => 'El tipo de perfil no es válido.',
        ];
    }
}
