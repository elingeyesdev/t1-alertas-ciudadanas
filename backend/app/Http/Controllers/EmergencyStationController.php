<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateEmergencyStationRequest;
use App\Http\Requests\UpdateEmergencyStationRequest;
use App\Services\EmergencyStationService;

class EmergencyStationController extends Controller
{
    public function __construct(private readonly EmergencyStationService $stations) {}

    public function index()
    {
        return $this->stations->findAll();
    }

    public function store(CreateEmergencyStationRequest $request)
    {
        return $this->stations->create($request->validated())->response()->setStatusCode(201);
    }

    public function update(UpdateEmergencyStationRequest $request, int $id)
    {
        return $this->stations->update($id, $request->input('name'));
    }

    public function destroy(int $id)
    {
        return $this->stations->remove($id);
    }
}
