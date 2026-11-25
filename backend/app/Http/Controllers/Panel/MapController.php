<?php

namespace App\Http\Controllers\Panel;

use App\Enums\StateReport;
use App\Http\Resources\EmergencyStationResource;
use App\Http\Resources\PublicAlertResource;
use App\Http\Resources\ReportResource;
use App\Models\EmergencyStation;
use App\Models\PublicAlert;
use App\Models\Report;
use App\Services\TrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class MapController extends PanelController
{
    public function index(): View
    {
        return view('panel.mapa');
    }

    /** Datos iniciales del mapa en vivo; luego se actualiza por Reverb. */
    public function data(TrackingService $trackings): JsonResponse
    {
        $reports = Report::with(['type', 'images'])
            ->where('status', StateReport::Activo)
            ->latest()
            ->get();

        return response()->json([
            'reports' => ReportResource::collection($reports)->resolve(),
            'stations' => EmergencyStationResource::collection(EmergencyStation::orderBy('id')->get())->resolve(),
            'trackings' => $trackings->all(),
            'public_alerts' => PublicAlertResource::collection(PublicAlert::active()->latest()->get())->resolve(),
        ]);
    }
}
