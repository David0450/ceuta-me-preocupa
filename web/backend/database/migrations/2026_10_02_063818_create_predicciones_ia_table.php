<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('predicciones_ia', function (Blueprint $table) {
            $table->id();

            $table->foreignId('sugerencia_id')
                  ->constrained('sugerencias')
                  ->cascadeOnDelete();

            // Apunta a categoria, subcategoria u organismo (ver morph map)
            $table->morphs('predecible');

            $table->decimal('confianza', 5, 4);
            $table->string('modelo_version');

            // pendiente | aceptada | rechazada
            $table->string('estado')->default('pendiente')->index();

            $table->foreignId('revisado_por')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            $table->timestamp('revisado_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('predicciones_ia');
    }
};
