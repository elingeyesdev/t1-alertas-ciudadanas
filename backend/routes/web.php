<?php

use App\Http\Controllers\AlertaController;
use App\Http\Controllers\Panel\DashboardController;
use App\Http\Controllers\Panel\IncidentReportsController;
use App\Http\Controllers\Panel\MapController;
use App\Http\Controllers\Panel\MissingCaseController;
use App\Http\Controllers\Panel\PublicAlertController;
use App\Http\Controllers\Panel\ReportController;
use App\Http\Controllers\Panel\StationController;
use App\Http\Controllers\Panel\UserController;
use App\Http\Controllers\PanelAuthController;
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
        Route::get('/', [DashboardController::class, 'index'])->name('index');
        Route::post('/logout', [PanelAuthController::class, 'destroy'])->name('logout');

        // Mapa en vivo
        Route::get('/mapa', [MapController::class, 'index'])->name('mapa');
        Route::get('/mapa/datos', [MapController::class, 'data'])->name('mapa.datos');

        Route::get('/reportes', [IncidentReportsController::class, 'index'])->name('reportes.index');
        Route::get('/reportes/excel', [IncidentReportsController::class, 'excel'])->name('reportes.excel');

        // Alertas (reportes)
        Route::controller(ReportController::class)->prefix('alertas')->name('alertas.')->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('/crear', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('/{id}', 'show')->whereNumber('id')->name('show');
            Route::patch('/{id}/verificar', 'verify')->whereNumber('id')->name('verify');
            Route::patch('/{id}/resolver', 'resolve')->whereNumber('id')->name('resolve');
            Route::patch('/{id}/reactivar', 'reactivate')->whereNumber('id')->name('reactivate');
            Route::delete('/{id}', 'destroy')->whereNumber('id')->name('destroy');
            Route::post('/{id}/despachos', 'storeDispatch')->whereNumber('id')->name('dispatch');
            Route::patch('/despachos/{dispatchId}', 'updateDispatch')->whereNumber('dispatchId')->name('dispatch.update');
        });

        // Usuarios
        Route::controller(UserController::class)->prefix('usuarios')->name('usuarios.')->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('/crear', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('/{id}/editar', 'edit')->whereUuid('id')->name('edit');
            Route::patch('/{id}', 'update')->whereUuid('id')->name('update');
            Route::delete('/{id}', 'destroy')->whereUuid('id')->name('destroy');
            Route::post('/{id}/correo', 'sendMail')->whereUuid('id')->name('mail');
            Route::post('/{id}/credenciales', 'resendCredentials')->whereUuid('id')->name('credentials');
        });

        // HU-32 Avisos preventivos (salud, viaje o desastres naturales)
        Route::controller(PublicAlertController::class)->prefix('avisos')->name('avisos.')->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('/crear', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::patch('/{id}/finalizar', 'finish')->whereNumber('id')->name('finish');
            Route::delete('/{id}', 'destroy')->whereNumber('id')->name('destroy');
        });

        Route::controller(MissingCaseController::class)->prefix('desaparecidos')->name('desaparecidos.')->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('/crear', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('/{case}', 'show')->whereNumber('case')->name('show');
            Route::get('/{case}/photos/{index}', 'showPhoto')->whereNumber(['case', 'index'])->name('photo');
            Route::get('/{case}/photos/{index}/image', 'photo')->whereNumber(['case', 'index'])->name('photo-image');
            Route::get('/{case}/contributions/{contribution}/photos/{index}', 'showContributionPhoto')
                ->whereNumber(['case', 'contribution', 'index'])->name('contribution-photo');
            Route::get('/{case}/contributions/{contribution}/photos/{index}/image', 'contributionPhoto')
                ->whereNumber(['case', 'contribution', 'index'])->name('contribution-photo-image');
            Route::get('/{case}/contributions/{contribution}/video', 'contributionVideo')
                ->whereNumber(['case', 'contribution'])->name('contribution-video');
            Route::patch('/{case}/aprobar', 'approve')->whereNumber('case')->name('approve');
            Route::patch('/{case}/rechazar', 'reject')->whereNumber('case')->name('reject');
            Route::patch('/{case}/encontrado', 'found')->whereNumber('case')->name('found');
        });

        // Estaciones de emergencia
        Route::controller(StationController::class)->prefix('estaciones')->name('estaciones.')->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('/crear', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('/{id}/editar', 'edit')->whereNumber('id')->name('edit');
            Route::patch('/{id}', 'update')->whereNumber('id')->name('update');
            Route::delete('/{id}', 'destroy')->whereNumber('id')->name('destroy');
        });
    });
});
