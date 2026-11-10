<?php

namespace App\Http\Controllers;

use App\Services\ImageService;

class ImageController extends Controller
{
    public function __construct(private readonly ImageService $images) {}

    public function destroy(int $id)
    {
        return $this->images->remove($id);
    }
}
