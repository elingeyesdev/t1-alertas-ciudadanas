<?php

namespace App\Http\Requests;

class ImageUploadRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'image' => ['required', 'file', 'image', 'max:10240'],
        ];
    }
}
