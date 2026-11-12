<?php

use App\Http\Controllers\AlertaController;
use App\Http\Controllers\PanelAuthController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json([
    'app' => 'API Alertas Ciudadanas (Laravel)',
    'api' => url('/api'),
    'panel' => url('/alertas'),
]));

// Panel de alertas recibidas (Blade)
Route::get('/alertas', [AlertaController::class, 'index'])->name('alertas.index');

Route::prefix('panel')->name('panel.')->group(function (): void {
    Route::get('/login', [PanelAuthController::class, 'create'])->name('login');
    Route::post('/login', [PanelAuthController::class, 'store'])->name('login.store');

    Route::middleware(['auth:web', 'role:admin'])->group(function (): void {
        Route::get('/', fn () => view('panel.index', ['user' => Auth::guard('web')->user()]))
            ->name('index');
        Route::post('/logout', [PanelAuthController::class, 'destroy'])->name('logout');
    });
});
