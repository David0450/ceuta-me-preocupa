<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contactos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('sugerencia_id')
                  ->unique()
                  ->constrained('sugerencias')
                  ->cascadeOnDelete();

            // Se cifra en la app con el cast 'encrypted'. El texto cifrado es mucho
            // más largo que el valor original, por eso 'text' y no 'string'.
            $table->text('canal');

            $table->boolean('consiente_respuesta')->default(false);
            $table->timestamp('fecha_consentimiento')->nullable();
            $table->boolean('respondido')->default(false);
            $table->timestamp('fecha_respuesta')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contactos');
    }
};
