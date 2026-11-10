<?php

namespace App\Http\Requests;

use App\Enums\StateType;
use Illuminate\Validation\Rule;

class UpdateDispatchStateRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'state' => ['required', Rule::enum(StateType::class)],
        ];
    }
}
