<?php

namespace App\Http\Requests;

class NearbyReportsRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'latitude' => ['required', 'numeric'],
            'longitude' => ['required', 'numeric'],
            'radius' => ['required', 'numeric', 'min:0'],
        ];
    }
}
