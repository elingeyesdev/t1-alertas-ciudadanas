<?php

namespace App\Http\Requests;

/**
 * Registro público (POST /api/auth/register).
 * No acepta el rol: cualquier "roleId" que envíe el cliente se ignora y
 * la cuenta siempre se crea como ciudadano (rol "usuario"). Así nadie
 * puede registrarse como autoridad o administrador.
 */
class PublicRegisterRequest extends RegisterUserRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['roleId']);

        return $rules;
    }
}
