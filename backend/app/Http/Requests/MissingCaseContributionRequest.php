<?php

namespace App\Http\Requests;

use Illuminate\Validation\Validator;

class MissingCaseContributionRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'message' => ['nullable', 'required_without_all:photos,video', 'string', 'max:2000'],
            'photos' => ['nullable', 'array', 'list', 'min:1', 'max:3'],
            'photos.*' => ['required', 'file', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
            'video' => ['nullable', 'file', 'mimetypes:video/mp4', 'max:20480'],
        ];
    }

    public function messages(): array
    {
        return [
            'message.required_without_all' => 'Escribe un comentario o adjunta una foto o un video.',
            'message.max' => 'El comentario admite hasta 2000 caracteres.',
            'photos.max' => 'Puedes adjuntar hasta tres fotos.',
            'photos.*.mimes' => 'Las fotos deben ser JPEG, PNG o WebP.',
            'photos.*.max' => 'Cada foto admite hasta 5 MB.',
            'video.mimetypes' => 'El video debe estar en formato MP4.',
            'video.max' => 'El video admite hasta 20 MB.',
        ] + parent::messages();
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $bytes = $this->file('video')?->getSize() ?? 0;
            foreach ($this->file('photos', []) ?? [] as $photo) {
                $bytes += $photo->getSize();
            }
            if ($bytes > 20 * 1024 * 1024) {
                $validator->errors()->add('video', 'Los archivos del aporte pueden sumar hasta 20 MB.');
            }
        }];
    }
}
