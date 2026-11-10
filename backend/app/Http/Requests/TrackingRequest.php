<?php

namespace App\Http\Requests;

class TrackingRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'userId' => ['required', 'string'],
            'data' => ['sometimes', 'array'],
        ];
    }
}
