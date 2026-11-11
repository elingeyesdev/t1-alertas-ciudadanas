<?php

namespace App\Http\Controllers;

use App\Http\Requests\NotifyRouteRequest;
use App\Services\NotificationService;

class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    /** POST /api/notifications/notify-route (autoridad, admin) */
    public function notifyRoute(NotifyRouteRequest $request)
    {
        $result = $this->notifications->notifyUsersAlongRoute(
            $request->input('route'),
            $request->user()->id,
            $request->input('incidentType'),
            $request->input('description'),
            $request->filled('reportId') ? (int) $request->input('reportId') : null,
        );

        return response()->json($result, 201);
    }
}
