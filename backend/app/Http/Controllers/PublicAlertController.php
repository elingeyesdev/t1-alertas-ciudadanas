<?php

namespace App\Http\Controllers;

use App\Http\Resources\PublicAlertResource;
use App\Services\PublicAlerts;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** HU-32: la app pide los avisos vigentes para dibujarlos en el mapa. */
class PublicAlertController extends Controller
{
    public function index(PublicAlerts $alerts): AnonymousResourceCollection
    {
        return PublicAlertResource::collection($alerts->active());
    }
}
