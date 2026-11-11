<?php

use App\Http\Controllers\AlertaController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json([
    'app' => 'API Alertas Ciudadanas (Laravel)',
    'api' => url('/api'),
    'panel' => url('/alertas'),
]));

// Panel de alertas recibidas (Blade)
Route::get('/alertas', [AlertaController::class, 'index'])->name('alertas.index');
