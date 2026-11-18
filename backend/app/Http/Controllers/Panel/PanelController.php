<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Base de los controladores del panel Blade/Tabler.
 * Reutiliza los mismos servicios que la API y convierte sus errores
 * (abort 400/404, llaves foráneas) en mensajes flash para el usuario.
 */
abstract class PanelController extends Controller
{
    protected function run(callable $action, string $success, ?string $redirectTo = null): RedirectResponse
    {
        try {
            $result = $action();
        } catch (HttpExceptionInterface $e) {
            return back()->withInput()->with('error', $e->getMessage() ?: 'No se pudo completar la acción.');
        } catch (QueryException $e) {
            if ($e->getCode() === '23503') {
                return back()->with('error', 'No se puede eliminar: tiene registros relacionados.');
            }
            throw $e;
        }

        $message = is_array($result) && isset($result['message']) && $success === '' ? $result['message'] : $success;
        $redirect = $redirectTo ? redirect($redirectTo) : back();

        return $redirect->with('success', $message);
    }
}
