<?php

namespace App\Http\Requests;

class CreateDispatchRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'reportId' => ['required', 'integer'],
            'destinationId' => ['required', 'integer'],
            'userId' => ['required', 'uuid'],
        ];
    }
}
