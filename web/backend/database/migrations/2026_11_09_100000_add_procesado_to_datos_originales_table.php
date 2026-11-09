<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marca de procesado para el pipeline de IA (ai/qwen_chat.py): lee las filas
 * con procesado = 0 y las pone a 1 en la misma transacción en la que inserta
 * su propuesta en propuestas_ia. Se comprueba antes si existe porque en
 * producción la columna pudo añadirse a mano desde phpMyAdmin.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('datos_originales', 'procesado')) {
            return;
        }

        Schema::table('datos_originales', function (Blueprint $table) {
            $table->boolean('procesado')->default(false)->index()->after('zona_origen');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('datos_originales', 'procesado')) {
            return;
        }

        Schema::table('datos_originales', function (Blueprint $table) {
            $table->dropIndex(['procesado']);
            $table->dropColumn('procesado');
        });
    }
};
