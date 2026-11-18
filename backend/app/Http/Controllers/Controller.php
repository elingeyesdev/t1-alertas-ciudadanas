<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

abstract class Controller
{
    /** Solo el dueño de la cuenta puede hacerlo (403 si no). */
    protected function ensureSelf(Request $request, string $userId): void
    {
        abort_unless($request->user()?->id === $userId, 403, 'Solo puedes modificar tu propia cuenta');
    }

    /** El dueño de la cuenta o un administrador. */
    protected function ensureSelfOrAdmin(Request $request, string $userId): void
    {
        $user = $request->user();
        abort_unless($user?->id === $userId || $user?->hasRole('admin'), 403, 'No tienes permiso para esta acción');
    }

    /** Autoridad o administrador. */
    protected function isStaff(?User $user): bool
    {
        return (bool) $user?->hasRole('autoridad', 'admin');
    }
}
