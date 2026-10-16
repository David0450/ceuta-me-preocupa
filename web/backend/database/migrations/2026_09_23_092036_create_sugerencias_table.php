<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sugerencias', function (Blueprint $table) {
            $table->id();
            $table->timestamp('fecha_recepcion');

            // Categoría única (obligatoria). No se permite borrar una categoría en uso.
            $table->foreignId('categoria_id')
                  ->constrained('categorias')
                  ->restrictOnDelete();

            // Organismo de acción. Si se borra el organismo, queda a null.
            $table->foreignId('organismo_id')
                  ->nullable()
                  ->constrained('organismos')
                  ->nullOnDelete();

            $table->enum('estado', [
                'recibido',
                'en_revision',
                'clasificado',
                'derivado',
                'cerrado',
            ])->default('recibido');

            $table->text('texto_desidentificado')->nullable();
            $table->text('resultado_esperado_desidentificado')->nullable();

            $table->string('version_aviso_privacidad');
            $table->timestamp('fecha_aceptacion');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sugerencias');
    }
};
