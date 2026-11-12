<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PanelAuthController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    public function create(): RedirectResponse|View
    {
        if (Auth::guard('web')->check()) {
            if (Auth::guard('web')->user()->hasRole('admin')) {
                return redirect()->route('panel.index');
            }

            Auth::guard('web')->logout();
        }

        return view('panel.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $key = $this->throttleKey($request, $credentials['phone']);
        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages(['phone' => 'No se pudo iniciar sesión. Verifica tus datos e inténtalo más tarde.']);
        }

        if (! Auth::guard('web')->attempt($credentials, $request->boolean('remember'))
            || ! Auth::guard('web')->user()->hasRole('admin')) {
            Auth::guard('web')->logout();
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages(['phone' => 'No se pudo iniciar sesión. Verifica tus datos e inténtalo más tarde.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        return redirect()->route('panel.index');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('panel.login');
    }

    private function throttleKey(Request $request, string $phone): string
    {
        return 'panel-login:'.sha1(mb_strtolower($phone).'|'.$request->ip());
    }
}
