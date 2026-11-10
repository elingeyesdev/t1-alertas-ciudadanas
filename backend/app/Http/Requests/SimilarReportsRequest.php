<?php

namespace App\Http\Requests;

/** Equivale a VerifyReportRequest (query string de GET /reports/similars) */
class SimilarReportsRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'userId' => ['nullable', 'string'], // la web no lo envía
            'type' => ['required', 'numeric'],
            'latitude' => ['required', 'numeric'],
            'longitude' => ['required', 'numeric'],
        ];
    }
}
