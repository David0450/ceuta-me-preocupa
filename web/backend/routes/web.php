<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\SugerenciaController;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/sugerencias', [SugerenciaController::class, 'store'])->name('sugerencias.store');
