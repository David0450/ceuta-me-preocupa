<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * predicciones_ia queda sustituida por propuestas_ia (una fila por ejecución
 * de la IA, que la IA rellena con un INSERT directo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('predicciones_ia');
    }

    public function down(): void
    {
        Schema::create('predicciones_ia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sugerencia_id')->constrained('sugerencias')->cascadeOnDelete();
            $table->morphs('predecible');
            $table->decimal('confianza', 5, 4);
            $table->string('modelo_version');
            $table->string('estado')->default('pendiente')->index();
            $table->foreignId('revisado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revisado_at')->nullable();
            $table->timestamps();
        });
    }
};
