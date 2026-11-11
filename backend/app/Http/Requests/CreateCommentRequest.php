<?php

namespace App\Http\Requests;

class CreateCommentRequest extends ApiRequest
{
    protected function prepareForValidation(): void
    {
        $this->clean(['text']);
    }

    public function rules(): array
    {
        return [
            'creatorId' => ['required', 'uuid'],
            'text' => ['required', 'string', 'max:250'],
        ];
    }
}
