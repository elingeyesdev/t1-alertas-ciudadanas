<?php

namespace App\Http\Requests;

use App\Enums\ProfileType;
use Illuminate\Validation\Rule;

/** Equivale a CreateAuthorityUserRequest */
class CreateAuthorityUserRequest extends RegisterUserRequest
{
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();
        $this->clean(['ci']);
        $this->clean(['gmail'], true);
    }

    public function rules(): array
    {
        return parent::rules() + [
            'ci' => ['required', 'string', 'max:255'],
            'gmail' => ['required', 'email', 'max:255'],
            'profile_type' => ['required', Rule::enum(ProfileType::class)],
        ];
    }

    public function messages(): array
    {
        return parent::messages() + [
            'profile_type.enum' => 'El tipo de perfil no es válido (policia, bombero, paramedico).',
        ];
    }
}
