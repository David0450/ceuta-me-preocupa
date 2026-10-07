<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campos de revisión humana exigidos por el protocolo (puntos 4.2 y 5):
 * revisión de privacidad, confirmación de la clasificación, urgencia,
 * "competencia por confirmar", duplicados, acción adoptada y
 * eliminación lógica de envíos indebidos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sugerencias', function (Blueprint $table) {
            // ordinaria | prioritaria | urgente
            $table->string('nivel_urgencia')->default('ordinaria')->index()->after('estado');

            // Cuando no se puede confirmar el organismo (información insuficiente
            // o varios niveles administrativos implicados).
            $table->boolean('competencia_por_confirmar')->default(false)->after('organismo_id');

            // Revisión humana de privacidad (la desidentificación automática no basta).
            $table->foreignId('privacidad_revisada_por')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamp('privacidad_revisada_at')->nullable();

            // Clasificación doble: la IA propone, una persona confirma o corrige.
            $table->foreignId('clasificacion_revisada_por')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamp('clasificacion_revisada_at')->nullable();

            // Deduplicación sin borrar el recuento de envíos.
            $table->foreignId('duplicado_de_id')->nullable()
                ->constrained('sugerencias')->nullOnDelete();

            $table->text('accion_adoptada')->nullable();

            // Eliminación lógica (papelera) de envíos indebidos.
            $table->string('motivo_eliminacion')->nullable();
            $table->foreignId('eliminada_por')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('sugerencias', function (Blueprint $table) {
            $table->dropForeign(['privacidad_revisada_por']);
            $table->dropForeign(['clasificacion_revisada_por']);
            $table->dropForeign(['duplicado_de_id']);
            $table->dropForeign(['eliminada_por']);

            $table->dropColumn([
                'nivel_urgencia',
                'competencia_por_confirmar',
                'privacidad_revisada_por',
                'privacidad_revisada_at',
                'clasificacion_revisada_por',
                'clasificacion_revisada_at',
                'duplicado_de_id',
                'accion_adoptada',
                'motivo_eliminacion',
                'eliminada_por',
                'deleted_at',
            ]);
        });
    }
};
