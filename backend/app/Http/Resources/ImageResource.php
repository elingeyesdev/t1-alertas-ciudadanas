<?php

namespace App\Http\Resources;

use App\Support\Dt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Equivale a ImageResponse */
class ImageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $by = $this->uploadedBy;

        return [
            'id' => $this->id,
            'url' => str_starts_with($this->cloudinary_id ?? '', 'local:')
                ? $request->getSchemeAndHttpHost().'/storage/'.substr($this->cloudinary_id, 6)
                : ($this->url ?? ''),
            'uploaded_at' => Dt::iso($this->uploaded_at),
            'uploadedBy' => $by
                ? ['id' => $by->id, 'first_name' => $by->first_name ?? '', 'last_name' => $by->last_name ?? '']
                : ['id' => '', 'first_name' => '—', 'last_name' => ''],
        ];
    }
}
