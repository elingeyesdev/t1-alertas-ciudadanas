<?php

namespace App\Http\Requests;

use App\Models\MissingCase;
use Illuminate\Validation\Rule;

class MissingCaseRequest extends ApiRequest
{
    public const RADII = [500 => '500 m', 1000 => '1 km', 2000 => '2 km', 5000 => '5 km', 10000 => '10 km', 30000 => '30 km'];

    protected function prepareForValidation(): void
    {
        $phone = $this->input('reference_phone');
        if (is_string($phone)) {
            $this->merge(['reference_phone' => preg_replace('/[ ()-]/', '', $phone)]);
        }
    }

    public function rules(): array
    {
        $fields = [
            'kind' => ['required', Rule::in(array_keys(MissingCase::KINDS))],
            'name' => ['required', 'string', 'max:120'],
            'place' => ['required', 'string', 'max:180'],
            'missing_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now('America/La_Paz')->toDateString()],
            'description' => ['required', 'string', 'max:2000'],
            'reference_phone' => ['required', 'string', 'regex:/^\+?[0-9]{7,15}$/D'],
            'photos' => ['required', 'array', 'list', 'min:1', 'max:3'],
            'photos.*' => ['required', 'file', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
        ];
        $location = [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ];
        $zone = $location + [
            'radius_m' => ['required', 'integer', Rule::in(array_keys(self::RADII))],
        ];

        return match ($this->route()?->getName()) {
            'panel.desaparecidos.approve' => $zone,
            'panel.desaparecidos.store' => $fields + $zone,
            default => $fields + $location,
        };
    }

    public function attributes(): array
    {
        return [
            'kind' => 'tipo',
            'name' => 'nombre',
            'place' => 'lugar',
            'missing_on' => 'fecha de desaparición',
            'description' => 'descripción',
            'reference_phone' => 'teléfono de referencia',
            'photos' => 'fotos',
            'photos.*' => 'foto',
            'latitude' => 'latitud',
            'longitude' => 'longitud',
            'radius_m' => 'radio',
        ];
    }

    public function messages(): array
    {
        return [
            'missing_on.date_format' => 'Indica una fecha de desaparición válida.',
            'missing_on.before_or_equal' => 'La fecha de desaparición no puede ser futura.',
            'reference_phone.regex' => 'Indica un teléfono de 7 a 15 dígitos.',
            'photos.max' => 'Puedes adjuntar hasta tres fotos.',
            'photos.*.mimes' => 'Las fotos deben ser JPEG, PNG o WebP.',
            'photos.*.max' => 'Cada foto admite hasta 5 MB.',
        ] + parent::messages();
    }
}
