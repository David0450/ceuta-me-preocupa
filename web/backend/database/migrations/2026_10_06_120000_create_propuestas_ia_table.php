<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Resultado de la IA para cada sugerencia: una fila por ejecución del modelo.
 *
 * La IA escribe aquí con un INSERT directo y no toca ninguna otra tabla.
 * El panel muestra siempre la propuesta más reciente y, cuando una persona
 * confirma la revisión, anota en esa misma fila en qué acertó el modelo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('propuestas_ia', function (Blueprint $table) {
            $table->id();

            $table->foreignId('sugerencia_id')
                ->constrained('sugerencias')
                ->cascadeOnDelete();

            /* --- Lo que escribe la IA --- */
            $table->foreignId('categoria_id')->nullable()
                ->constrained('categorias')->nullOnDelete();
            // El protocolo permite como máximo dos subcategorías.
            $table->foreignId('subcategoria_1_id')->nullable()
                ->constrained('subcategorias')->nullOnDelete();
            $table->foreignId('subcategoria_2_id')->nullable()
                ->constrained('subcategorias')->nullOnDelete();
            $table->foreignId('organismo_id')->nullable()
                ->constrained('organismos')->nullOnDelete();
            // ordinaria | prioritaria | urgente
            $table->string('nivel_urgencia')->nullable();
            $table->text('texto_desidentificado')->nullable();
            $table->text('propuesta_desidentificada')->nullable();
            $table->string('modelo_version')->nullable();
            // Con valor por defecto para que el INSERT no tenga que rellenarla.
            $table->timestamp('created_at')->useCurrent();

            /* --- Lo que rellena el panel al revisar (métricas del modelo) --- */
            $table->foreignId('revisada_por')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamp('revisada_at')->nullable();
            $table->boolean('categoria_correcta')->nullable();
            $table->boolean('subcategorias_correctas')->nullable();
            $table->boolean('organismo_correcto')->nullable();
            $table->boolean('urgencia_correcta')->nullable();
            $table->boolean('texto_editado')->nullable();

            $table->index(['sugerencia_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('propuestas_ia');
    }
};
