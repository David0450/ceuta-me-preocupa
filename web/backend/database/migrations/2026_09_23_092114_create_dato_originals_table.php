<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('datos_originales', function (Blueprint $table) {
            $table->id();

            // 1:1 con sugerencias. Si se borra la comunicación, se borran sus datos.
            // (El borrado de retención va al revés: se elimina esta fila y la sugerencia permanece.)
            $table->foreignId('sugerencia_id')
                  ->unique()
                  ->constrained('sugerencias')
                  ->cascadeOnDelete();

            $table->text('texto_original');
            $table->text('resultado_esperado_original')->nullable();
            $table->string('organismo_sugerido_ciudadano')->nullable();
            $table->string('zona_origen')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('datos_originales');
    }
};
