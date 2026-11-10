<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateDispatchRequest;
use App\Http\Requests\UpdateDispatchStateRequest;
use App\Services\DispatchService;

class DispatchController extends Controller
{
    public function __construct(private readonly DispatchService $dispatches) {}

    public function store(CreateDispatchRequest $request)
    {
        return $this->dispatches->create(
            (int) $request->input('reportId'),
            (int) $request->input('destinationId'),
            $request->input('userId'),
        )->response()->setStatusCode(201);
    }

    public function updateState(UpdateDispatchStateRequest $request, int $id)
    {
        return $this->dispatches->updateState($id, $request->input('state'));
    }
}
