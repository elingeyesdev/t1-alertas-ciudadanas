<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateDispatchRequest;
use App\Http\Requests\UpdateDispatchStateRequest;
use App\Services\DispatchService;
use App\Enums\StateType;
use App\Http\Resources\DispatchResource;
use App\Models\Dispatch;
use Illuminate\Http\Request;

class DispatchController extends Controller
{
    public function __construct(private readonly DispatchService $dispatches) {}

    public function store(CreateDispatchRequest $request)
    {
        return $this->dispatches->create(
            (int) $request->input('reportId'),
            (int) $request->input('destinationId'),
            $request->user()->id, // quien atiende es el usuario del token
        )->response()->setStatusCode(201);
    }

    public function updateState(UpdateDispatchStateRequest $request, int $id)
    {
        return $this->dispatches->updateState($id, $request->input('state'), $request->user());
    }

    public function showAssignment(Request $request, Dispatch $dispatch): DispatchResource
    {
        abort_unless($dispatch->attended_by === $request->user()->id, 404);
        abort_unless($dispatch->state === StateType::EnCurso, 409, 'El despacho ya no está en curso.');

        return new DispatchResource($dispatch->load('report.type', 'destination'));
    }
}
