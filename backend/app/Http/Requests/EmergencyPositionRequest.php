<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class EmergencyPositionRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'latitude' => ['required', 'numeric', 'between:-85,85'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy_m' => ['required', 'numeric', 'between:0,50'],
            'captured_at' => ['required', 'date'],
        ];
    }

    public function position(): array
    {
        $data = $this->validated();
        $capturedAt = CarbonImmutable::parse($data['captured_at'])->utc();
        if ($capturedAt->lt(now()->subSeconds(30)) || $capturedAt->gt(now()->addSeconds(5))) {
            throw ValidationException::withMessages(['captured_at' => 'La posición GPS debe ser reciente.']);
        }

        return [
            'latitude' => (float) $data['latitude'],
            'longitude' => (float) $data['longitude'],
            'accuracy_m' => (float) $data['accuracy_m'],
            'captured_at' => $capturedAt->format('Y-m-d\TH:i:s.uP'),
        ];
    }
}
