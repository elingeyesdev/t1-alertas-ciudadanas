<?php

namespace App\Services;

use App\Models\Role;

class RoleService
{
    public function create(string $name): Role
    {
        return Role::create(['name' => $name]);
    }

    public function findAll()
    {
        return Role::orderBy('id')->get();
    }

    public function remove(int $id): array
    {
        $item = Role::find($id);
        if ($item && $item->users()->withoutGlobalScopes()->exists()) {
            abort(409, 'El rol tiene usuarios asignados y no se puede eliminar');
        }

        if (Role::whereKey($id)->delete() === 0) {
            abort(404, "El rol con ID {$id} no se encontro");
        }

        return ['message' => 'Rol eliminado correctamente'];
    }
}
