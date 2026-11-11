<?php

namespace App\Services;

use App\Models\Image;
use App\Models\Report;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Sube imágenes a Cloudinary usando su API REST (sin SDK).
 * Si Cloudinary no está configurado (o dice "pendiente") las guarda en
 * storage/app/public/reports y las sirve desde /storage/reports/...
 */
class ImageService
{
    public function cloudinaryEnabled(): bool
    {
        $c = config('alertas.cloudinary');

        return filled($c['cloud_name']) && filled($c['api_key']) && filled($c['api_secret'])
            && $c['cloud_name'] !== 'pendiente';
    }

    /** @return array{url: string, public_id: string} */
    public function upload(UploadedFile $file): array
    {
        if (! $this->cloudinaryEnabled()) {
            $path = $file->store('reports', 'public');

            return ['url' => Storage::disk('public')->url($path), 'public_id' => 'local:'.$path];
        }

        $c = config('alertas.cloudinary');
        // Misma transformación que en Nest: ancho máx 1200 y conversión a webp
        $params = [
            'folder' => $c['folder'],
            'timestamp' => time(),
            'transformation' => 'c_limit,w_1200/f_webp,q_auto:good',
        ];

        $response = Http::asMultipart()
            ->timeout(60)
            ->attach('file', fopen($file->getRealPath(), 'r'), $file->getClientOriginalName())
            ->post("https://api.cloudinary.com/v1_1/{$c['cloud_name']}/image/upload", $params + [
                'api_key' => $c['api_key'],
                'signature' => $this->sign($params, $c['api_secret']),
            ]);

        if ($response->failed()) {
            Log::error('Error subiendo imagen a Cloudinary', ['body' => $response->body()]);
            abort(400, 'Error al subir la imagen');
        }

        return ['url' => $response->json('secure_url'), 'public_id' => $response->json('public_id')];
    }

    public function createFromReport(Report $report, User $user, UploadedFile $file): Image
    {
        $result = $this->upload($file);

        return Image::create([
            'cloudinary_id' => $result['public_id'],
            'url' => $result['url'],
            'uploaded_at' => now(),
            'report_id' => $report->id,
            'uploaded_by' => $user->id,
        ]);
    }

    public function remove(int $id): array
    {
        $image = Image::find($id);
        if (! $image) {
            abort(404, "La imagen con ID {$id} no se encontró");
        }

        try {
            $this->destroyFile($image->cloudinary_id);
        } catch (Throwable $e) {
            Log::error('Error al eliminar la imagen', ['error' => $e->getMessage()]);
            abort(500, 'Error al eliminar la imagen de Cloudinary');
        }

        $image->delete();

        return ['message' => 'Imagen eliminada correctamente'];
    }

    private function destroyFile(?string $publicId): void
    {
        if (! $publicId) {
            return;
        }

        if (str_starts_with($publicId, 'local:')) {
            Storage::disk('public')->delete(substr($publicId, 6));

            return;
        }

        if (! $this->cloudinaryEnabled()) {
            return;
        }

        $c = config('alertas.cloudinary');
        $params = ['public_id' => $publicId, 'timestamp' => time()];
        Http::asForm()->post("https://api.cloudinary.com/v1_1/{$c['cloud_name']}/image/destroy", $params + [
            'api_key' => $c['api_key'],
            'signature' => $this->sign($params, $c['api_secret']),
        ])->throw();
    }

    /** Firma de Cloudinary: sha1 de los parámetros ordenados + api_secret */
    private function sign(array $params, string $secret): string
    {
        ksort($params);
        $toSign = collect($params)->map(fn ($v, $k) => "{$k}={$v}")->implode('&');

        return sha1($toSign.$secret);
    }
}
