<?php

namespace App\Http\Requests;

/** Para crear roles y tipos de reporte: { "name": "..." } */
class NameRequest extends ApiRequest
{
    protected function prepareForValidation(): void
    {
        $this->clean(['name']);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'base_weight' => ['sometimes', 'integer', 'min:1', 'max:10'],
        ];
    }
}
