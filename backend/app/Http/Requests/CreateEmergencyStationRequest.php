<?php

namespace App\Http\Requests;

use App\Enums\InstallationType;
use Illuminate\Validation\Rule;

class CreateEmergencyStationRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'installation_type' => ['required', Rule::enum(InstallationType::class)],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ];
    }

    public function messages(): array
    {
        return parent::messages() + [
            'name.string' => 'El nombre debe ser una cadena de texto.',
            'installation_type.enum' => 'El tipo de instalación no es válido.',
            'latitude.numeric' => 'La latitud debe ser un número.',
            'longitude.numeric' => 'La longitud debe ser un número.',
        ];
    }
}
