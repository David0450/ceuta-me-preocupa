<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de auditoría de la revisión: quién hizo qué y cuándo.
 * No guarda textos ni datos personales, solo metadatos del cambio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historial_sugerencias', function (Blueprint $table) {
            $table->id();

            // Sin clave foránea a propósito: la traza debe sobrevivir
            // a la eliminación definitiva de la sugerencia.
            $table->unsignedBigInteger('sugerencia_id')->index();

            $table->foreignId('user_id')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->string('accion')->index();
            $table->json('cambios')->nullable();
            $table->text('motivo')->nullable();

            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historial_sugerencias');
    }
};
