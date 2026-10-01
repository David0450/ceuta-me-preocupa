<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SugerenciaController;
use App\Http\Controllers\Admin\SugerenciaAdminController;

Route::get('/', [SugerenciaController::class, 'create'])->name('sugerencias.create');
Route::post('/sugerencias', [SugerenciaController::class, 'store'])
    ->middleware('throttle:buzon')
    ->name('sugerencias.store');


Route::get('/sugerencias/{sugerencia}', [SugerenciaAdminController::class, 'show'])->name('admin.sugerencias.show')->middleware(['auth', 'verified']);

Route::view('/privacidad', 'privacidad')->name('privacidad');

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/sugerencias', [SugerenciaAdminController::class, 'index'])->name('admin.sugerencias.index')->middleware(['auth', 'verified']);

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/auth.php';
