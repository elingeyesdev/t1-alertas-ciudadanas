<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Base para todas las validaciones (equivale a los DTO + class-validator). */
abstract class ApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Recorta espacios y opcionalmente pasa a minúsculas (como @Transform en Nest). */
    protected function clean(array $fields, bool $lower = false): void
    {
        $merge = [];
        foreach ($fields as $field) {
            $value = $this->input($field);
            if (is_string($value)) {
                $value = trim($value);
                $merge[$field] = $lower ? mb_strtolower($value) : $value;
            }
        }
        $this->merge($merge);
    }

    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'string' => 'El campo :attribute debe ser una cadena de texto.',
            'integer' => 'El campo :attribute debe ser un número entero.',
            'numeric' => 'El campo :attribute debe ser un número.',
            'uuid' => 'El campo :attribute debe ser un UUID válido.',
            'email' => 'Debe ingresar un correo electrónico válido.',
            'max.string' => 'El campo :attribute no puede superar los :max caracteres.',
            'min.string' => 'El campo :attribute debe tener al menos :min caracteres.',
            'in' => 'El valor de :attribute no es válido.',
            'array' => 'El campo :attribute debe ser una lista.',
            'image' => 'El archivo debe ser una imagen.',
        ];
    }
}
