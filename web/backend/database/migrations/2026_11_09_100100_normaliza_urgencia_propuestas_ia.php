<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Hasta la 2.0.0 el pipeline de IA guardaba la urgencia como "alta" (emergencias)
 * o "media" (por defecto), valores que la web no reconoce: el panel los ignoraba
 * y una emergencia detectada por la IA no subía en la lista. Se pasan a los
 * valores de la web: alta -> urgente, media -> ordinaria.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('propuestas_ia')->where('nivel_urgencia', 'alta')->update(['nivel_urgencia' => 'urgente']);
        DB::table('propuestas_ia')->where('nivel_urgencia', 'media')->update(['nivel_urgencia' => 'ordinaria']);
    }

    public function down(): void
    {
        // No se puede saber qué filas tenían los valores antiguos; no se deshace.
    }
};
