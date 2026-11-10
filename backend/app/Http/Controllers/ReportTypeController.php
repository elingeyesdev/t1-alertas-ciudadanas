<?php

namespace App\Http\Controllers;

use App\Http\Requests\NameRequest;
use App\Services\ReportTypeService;

class ReportTypeController extends Controller
{
    public function __construct(private readonly ReportTypeService $types) {}

    public function store(NameRequest $request)
    {
        $type = $this->types->create($request->input('name'), (int) $request->input('base_weight', 1));

        return response()->json($type, 201);
    }

    public function index()
    {
        return $this->types->findAll();
    }

    public function destroy(int $id)
    {
        return $this->types->remove($id);
    }
}
