<?php

namespace App\Http\Controllers;

use App\Http\Requests\TrackingRequest;
use App\Services\TrackingService;

/**
 * Reemplaza los mensajes socket.io "getTrackings", "startTracking" y
 * "stopTracking" del TrackingsGateway por endpoints REST. Los cambios se
 * siguen emitiendo en tiempo real por el canal "trackings".
 */
class TrackingController extends Controller
{
    public function __construct(private readonly TrackingService $trackings) {}

    public function index()
    {
        return response()->json($this->trackings->all());
    }

    public function start(TrackingRequest $request)
    {
        $data = $request->has('data')
            ? (array) $request->input('data')
            : $request->except('userId');

        return response()->json($this->trackings->save((string) $request->input('userId'), $data), 201);
    }

    public function stop(TrackingRequest $request)
    {
        $userId = (string) $request->input('userId');
        $this->trackings->delete($userId);

        return response()->json(['userId' => $userId], 201);
    }
}
