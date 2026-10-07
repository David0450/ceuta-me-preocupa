<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Gate;
use App\Models\User;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // La sesión de MySQL usa la misma hora que la aplicación (Europe/Madrid),
        // así coinciden las fechas que escribe Laravel y las que pone MySQL por
        // defecto (CURRENT_TIMESTAMP, p. ej. en los INSERT de la IA). El desfase
        // se calcula en cada petición para respetar el cambio de hora.
        foreach (['mysql', 'mariadb'] as $conexion) {
            config(["database.connections.{$conexion}.timezone" => now()->format('P')]);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);

        RateLimiter::for('buzon', function (Request $request) {
            return [
                Limit::perMinute(3)->by($request->ip()),
                Limit::perHour(10)->by($request->ip()),
            ];
        });

        // Acceso al panel de revisión: solo cuentas marcadas como revisoras.
        Gate::define('revisar-sugerencias', fn (User $user): bool => (bool) $user->es_revisor);
    }
}
