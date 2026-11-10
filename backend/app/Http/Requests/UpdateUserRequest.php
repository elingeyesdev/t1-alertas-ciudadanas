<?php

namespace App\Http\Requests;

class UpdateUserRequest extends ApiRequest
{
    protected function prepareForValidation(): void
    {
        $this->clean(['first_name', 'last_name'], true);
    }

    public function rules(): array
    {
        return [
            'first_name' => ['sometimes', 'string', 'max:255'],
            'last_name' => ['sometimes', 'string', 'max:255'],
        ];
    }
}
