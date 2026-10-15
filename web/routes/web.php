<?php

use App\Http\Controllers\AlertaController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/alertas', [AlertaController::class, 'index'])->name('alertas.index');
