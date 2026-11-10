<?php

namespace App\Http\Controllers;

use App\Http\Requests\NameRequest;
use App\Services\RoleService;

class RoleController extends Controller
{
    public function __construct(private readonly RoleService $roles) {}

    public function store(NameRequest $request)
    {
        return response()->json($this->roles->create($request->input('name')), 201);
    }

    public function index()
    {
        return $this->roles->findAll();
    }

    public function destroy(int $id)
    {
        return $this->roles->remove($id);
    }
}
