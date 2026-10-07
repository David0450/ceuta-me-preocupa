<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SugerenciaController;
use App\Http\Controllers\Admin\SugerenciaAdminController;

Route::get('/', [SugerenciaController::class, 'create'])->name('sugerencias.create');
Route::post('/sugerencias', [SugerenciaController::class, 'store'])
    ->middleware('throttle:buzon')
    ->name('sugerencias.store');



Route::view('/privacidad', 'privacidad')->name('privacidad');

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// Panel de revisión: solo usuarios con es_revisor = true (ver AppServiceProvider).
Route::middleware(['auth', 'verified', 'can:revisar-sugerencias'])->group(function () {
    Route::get('/sugerencias', [SugerenciaAdminController::class, 'index'])->name('admin.sugerencias.index');
    // withTrashed: permite abrir también las sugerencias que están en la papelera.
    Route::get('/sugerencias/{sugerencia}', [SugerenciaAdminController::class, 'show'])
        ->withTrashed()
        ->name('admin.sugerencias.show');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/auth.php';
