<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AuthorityProfileController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\DispatchController;
use App\Http\Controllers\EmergencyStationController;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\MailController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReportTypeController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\TrackingController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
| Todas las rutas tienen el prefijo /api (igual que setGlobalPrefix('/api')).
| Rutas públicas = las que tenían @Public() en NestJS.
| El resto exige "Authorization: Bearer <access_token>" (middleware auth:api).
| ->middleware('role:admin') equivale a @Roles('admin').
*/

// ---------- Públicas ----------
Route::prefix('auth')->controller(AuthController::class)->group(function () {
    Route::post('register', 'register');
    Route::post('login', 'login');
    Route::post('refresh', 'refresh');
});

// ---------- Protegidas con JWT ----------
Route::middleware('auth:api')->group(function () {

    Route::get('/', fn () => 'Hello World!')->middleware('role:admin');

    // Auth
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::post('auth/logout', [AuthController::class, 'logout']);

    // Usuarios
    Route::prefix('users')->controller(UserController::class)->group(function () {
        Route::post('/', 'store')->middleware('role:admin');              // usado por la web (CreateUserSheet)
        Route::post('authority', 'createAuthority')->middleware('role:admin');
        Route::get('/', 'index');
        Route::get('{id}', 'show')->whereUuid('id');
        Route::patch('{id}', 'update')->whereUuid('id');
        Route::patch('{id}/fcm-token', 'updateFcmToken')->whereUuid('id');
        Route::patch('{id}/location', 'updateLocation')->whereUuid('id');
        Route::patch('{id}/password', 'updatePassword')->whereUuid('id');
        Route::delete('{id}', 'destroy')->whereUuid('id')->middleware('role:admin');
    });

    // Perfil de autoridad (el {id} es el id del USUARIO)
    Route::patch('authority-profile/{id}', [AuthorityProfileController::class, 'update'])->whereUuid('id');

    // Roles
    Route::prefix('roles')->controller(RoleController::class)->group(function () {
        Route::post('/', 'store')->middleware('role:admin');
        Route::get('/', 'index');
        Route::delete('{id}', 'destroy')->whereNumber('id')->middleware('role:admin');
    });

    // Tipos de reporte
    Route::prefix('report-types')->controller(ReportTypeController::class)->group(function () {
        Route::post('/', 'store')->middleware('role:admin');
        Route::get('/', 'index');
        Route::delete('{id}', 'destroy')->whereNumber('id')->middleware('role:admin');
    });

    // Reportes
    Route::prefix('reports')->controller(ReportController::class)->group(function () {
        Route::post('/', 'store');
        Route::get('/', 'index');
        Route::get('with-deleted', 'withDeleted');
        Route::get('nearby', 'nearby');
        Route::get('similars', 'similars');
        Route::get('user/{userId}', 'byUser')->whereUuid('userId');
        Route::get('{id}', 'show')->whereNumber('id');
        Route::delete('{id}', 'destroy')->whereNumber('id');
        Route::post('{id}/comments', 'storeComment')->whereNumber('id');
        Route::get('{id}/comments', 'comments')->whereNumber('id');
        Route::post('{id}/images/{userId}', 'uploadImage')->whereNumber('id')->whereUuid('userId');
        Route::patch('{id}/verify', 'verify')->whereNumber('id')->middleware('role:admin,autoridad');
        Route::patch('{id}/resolve', 'resolve')->whereNumber('id');
        Route::patch('{id}/reactivate', 'reactivate')->whereNumber('id');
    });

    // Comentarios (respuestas)
    Route::post('comments/{id}/replies', [CommentController::class, 'storeReply'])->whereNumber('id');
    Route::get('comments/{id}/replies', [CommentController::class, 'replies'])->whereNumber('id');

    // Imágenes
    Route::delete('images/{id}', [ImageController::class, 'destroy'])->whereNumber('id')->middleware('role:admin');

    // Estaciones de emergencia
    Route::prefix('emergency-stations')->controller(EmergencyStationController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::patch('{id}', 'update')->whereNumber('id');
        Route::delete('{id}', 'destroy')->whereNumber('id');
    });

    // Despachos
    Route::post('dispatches', [DispatchController::class, 'store']);
    Route::patch('dispatches/{id}/state', [DispatchController::class, 'updateState'])->whereNumber('id');

    // Correo
    Route::post('mail/{id}', [MailController::class, 'send'])->whereUuid('id');
    Route::post('mail/{id}/resend-credentials', [MailController::class, 'resendCredentials'])->whereUuid('id');

    // Notificaciones push
    Route::post('notifications/notify-route', [NotificationController::class, 'notifyRoute'])
        ->middleware('role:autoridad,admin');

    // Tracking en vivo de unidades (antes era por socket.io)
    Route::get('trackings', [TrackingController::class, 'index']);
    Route::post('trackings/start', [TrackingController::class, 'start']);
    Route::post('trackings/stop', [TrackingController::class, 'stop']);
});
