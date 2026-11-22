<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class IncidentReportFilters extends FormRequest
{
    protected $redirectRoute = 'panel.reportes.index';

    protected function prepareForValidation(): void
    {
        if ($this->missing('desde') && $this->missing('hasta')) {
            $today = Carbon::today(config('alertas.display_timezone'));
            $this->merge([
                'desde' => $today->copy()->subDays(29)->toDateString(),
                'hasta' => $today->toDateString(),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'desde' => ['required', 'date_format:Y-m-d'],
            'hasta' => ['required', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'tipo' => ['nullable', 'integer', 'exists:report_types,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'desde.required' => 'Indica la fecha de inicio.',
            'desde.date_format' => 'La fecha de inicio no es válida.',
            'hasta.required' => 'Indica la fecha de fin.',
            'hasta.date_format' => 'La fecha de fin no es válida.',
            'hasta.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la de inicio.',
            'tipo.integer' => 'Selecciona un tipo de incidente válido.',
            'tipo.exists' => 'Selecciona un tipo de incidente válido.',
        ];
    }
}
