<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Guarda la categoría y subcategorías que eligió la persona al enviar la
 * sugerencia. categoria_id y la tabla pivote pasan a ser la clasificación de
 * trabajo (la que corrige el revisor), así que sin estas columnas la elección
 * original se perdía al confirmar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sugerencias', function (Blueprint $table) {
            $table->foreignId('categoria_ciudadano_id')->nullable()->after('categoria_id')
                ->constrained('categorias')->nullOnDelete();
            $table->json('subcategorias_ciudadano')->nullable()->after('categoria_ciudadano_id');
        });

        $this->rellenarSugerenciasExistentes();
    }

    public function down(): void
    {
        Schema::table('sugerencias', function (Blueprint $table) {
            $table->dropForeign(['categoria_ciudadano_id']);
            $table->dropColumn(['categoria_ciudadano_id', 'subcategorias_ciudadano']);
        });
    }

    /**
     * Para las sugerencias que ya existen: si nadie ha confirmado todavía la
     * clasificación, la actual es la del ciudadano. Si ya se confirmó, se intenta
     * recuperar del historial el valor anterior a la primera confirmación.
     */
    private function rellenarSugerenciasExistentes(): void
    {
        $categorias = DB::table('categorias')->pluck('id', 'nombre');
        $subcategorias = DB::table('subcategorias')->get(['id', 'categoria_id', 'nombre']);

        DB::table('sugerencias')->orderBy('id')->each(function (object $s) use ($categorias, $subcategorias) {
            $categoriaId = $s->categoria_id;
            $subIds = DB::table('sugerencia_subcategoria')->where('sugerencia_id', $s->id)
                ->pluck('subcategoria_id')->map(fn ($id) => (int) $id)->all();

            if ($s->clasificacion_revisada_at !== null) {
                $primera = DB::table('historial_sugerencias')
                    ->where('sugerencia_id', $s->id)
                    ->where('accion', 'clasificacion_confirmada')
                    ->orderBy('id')
                    ->value('cambios');
                $cambios = $primera ? json_decode($primera, true) : [];

                if (isset($cambios['categoria'][0], $categorias[$cambios['categoria'][0]])) {
                    $categoriaId = $categorias[$cambios['categoria'][0]];
                }
                if (isset($cambios['subcategorias'][0]) && is_array($cambios['subcategorias'][0])) {
                    $subIds = $subcategorias
                        ->where('categoria_id', $categoriaId)
                        ->whereIn('nombre', $cambios['subcategorias'][0])
                        ->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
                }
            }

            DB::table('sugerencias')->where('id', $s->id)->update([
                'categoria_ciudadano_id' => $categoriaId,
                'subcategorias_ciudadano' => json_encode($subIds),
            ]);
        });
    }
};
