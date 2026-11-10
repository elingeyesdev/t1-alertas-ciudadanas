<?php

namespace App\Http\Requests;

class NotifyRouteRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'route' => ['required', 'array', 'min:2'],
            'route.*.lat' => ['required', 'numeric'],
            'route.*.lng' => ['required', 'numeric'],
            'incidentType' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'reportId' => ['nullable', 'integer'],
        ];
    }
}
