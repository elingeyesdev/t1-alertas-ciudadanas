<?php

namespace App\Http\Controllers;

use App\Http\Requests\TrackingRequest;
use App\Services\CaseTracking;
use App\Services\TrackingService;

/**
 * Reemplaza los mensajes socket.io "getTrackings", "startTracking" y
 * "stopTracking" del TrackingsGateway por endpoints REST. Los cambios se
 * siguen emitiendo en tiempo real por el canal "trackings".
 */
class TrackingController extends Controller
{
    public function __construct(
        private readonly TrackingService $trackings,
        private readonly CaseTracking $cases,
    ) {}

    public function index()
    {
        return response()->json($this->trackings->all());
    }

    public function start(TrackingRequest $request)
    {
        $data = $request->has('data')
            ? (array) $request->input('data')
            : $request->except('userId');

        // HU-26: cuando la unidad asignada arranca la navegación, el caso pasa a «en camino»
        if (($data['status'] ?? null) === 'active' && is_numeric($data['reportId'] ?? null)) {
            $this->cases->markEnRouteForReport((int) $data['reportId'], $request->user());
        }

        return response()->json($this->trackings->save((string) $request->input('userId'), $data), 201);
    }

    public function stop(TrackingRequest $request)
    {
        $userId = (string) $request->input('userId');
        $this->trackings->delete($userId);

        return response()->json(['userId' => $userId], 201);
    }
}
