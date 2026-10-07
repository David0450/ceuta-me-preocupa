<?php

use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Alta y baja de cuentas revisoras del panel (el registro público está desactivado).
 *   php artisan buzon:revisor ana@ugr.es            → da permiso (crea la cuenta si no existe)
 *   php artisan buzon:revisor ana@ugr.es --quitar   → retira el permiso
 */
Artisan::command('buzon:revisor {email} {--quitar : Retira el permiso de revisión}', function (string $email) {
    $user = User::where('email', $email)->first();

    if ($this->option('quitar')) {
        if (! $user) {
            $this->error("No existe ninguna cuenta con el correo {$email}.");

            return 1;
        }

        $user->forceFill(['es_revisor' => false])->save();
        $this->info("{$email} ya no puede acceder al panel de revisión.");

        return 0;
    }

    if (! $user) {
        if (! $this->confirm("No existe ninguna cuenta con {$email}. ¿Crearla?", true)) {
            return 1;
        }

        $nombre = $this->ask('Nombre', Str::before($email, '@'));
        $password = $this->secret('Contraseña (mínimo 8 caracteres)');

        if (strlen((string) $password) < 8) {
            $this->error('La contraseña debe tener al menos 8 caracteres.');

            return 1;
        }

        $user = User::create(['name' => $nombre, 'email' => $email, 'password' => $password]);
    }

    $user->forceFill(['es_revisor' => true])->save();
    $this->info("{$email} puede acceder al panel de revisión.");

    return 0;
})->purpose('Concede o retira el permiso de revisión de sugerencias');
