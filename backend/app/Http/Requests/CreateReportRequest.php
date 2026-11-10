<?php

namespace App\Http\Requests;

/** Equivale a CreateReportRequest (multipart/form-data con campo "image") */
class CreateReportRequest extends ApiRequest
{
    protected function prepareForValidation(): void
    {
        $this->clean(['description', 'zone', 'userId']);
    }

    public function rules(): array
    {
        return [
            'image' => ['nullable', 'file', 'image', 'max:10240'], // la app puede enviar el reporte sin foto
            'type' => ['required', 'integer', 'min:1'],
            'description' => ['required', 'string', 'max:250'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'userId' => ['required', 'uuid'],
            'zone' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return parent::messages() + [
            'type.integer' => 'El tipo de alerta no es válido',
            'type.min' => 'Debes elegir un tipo de alerta',
            'type.required' => 'Debes elegir un tipo de alerta',
            'description.required' => 'La descripción es obligatoria',
            'description.string' => 'La descripción debe ser texto',
            'description.max' => 'La descripción no puede superar los 250 caracteres',
        ];
    }
}
