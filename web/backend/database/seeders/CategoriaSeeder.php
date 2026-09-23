<?php

namespace Database\Seeders;

use App\Models\Categoria;
use App\Models\Subcategoria;
use Illuminate\Database\Seeder;

class CategoriaSeeder extends Seeder
{
    /**
     * Categorías y subcategorías mínimas del buzón,
     * según el punto 2 del Protocolo del buzón virtual ciudadano de Ceuta.
     */
    public function run(): void
    {
        $categorias = [
            'Urbanismo' => [
                'Planeamiento', 'Licencias', 'Vivienda', 'Obras',
                'Accesibilidad urbana', 'Patrimonio',
            ],
            'Movilidad' => [
                'Transporte público', 'Tráfico', 'Aparcamiento',
                'Itinerarios peatonales', 'Accesibilidad', 'Carga y descarga',
            ],
            'Educación no universitaria' => [
                'Centros', 'Escolarización', 'Profesorado', 'Currículo',
                'Formación profesional', 'Convivencia escolar',
            ],
            'Universidad' => [
                'Docencia', 'Investigación', 'Becas',
                'Instalaciones', 'Vida universitaria',
            ],
            'Economía' => [
                'Empleo', 'Comercio', 'Emprendimiento', 'Turismo',
                'Ayudas', 'Transformación económica',
            ],
            'Sanidad' => [
                'Salud pública', 'Atención sanitaria',
                'Accesibilidad sanitaria', 'Prevención',
            ],
            'Seguridad' => [
                'Seguridad ciudadana', 'Seguridad de instalaciones',
                'Policía local', 'Prevención',
            ],
            'Servicios Sociales' => [
                'Atención social', 'Dependencia', 'Inclusión',
                'Vulnerabilidad', 'Igualdad', 'Apoyo comunitario',
            ],
            'Medioambiente' => [
                'Residuos', 'Limpieza', 'Ruido', 'Zonas verdes',
                'Contaminación', 'Sostenibilidad',
            ],
        ];

        foreach ($categorias as $nombreCategoria => $subcategorias) {
            // firstOrCreate hace el seeder idempotente: si lo ejecutas
            // de nuevo no duplica ni falla por el índice único de nombre.
            $categoria = Categoria::firstOrCreate(['nombre' => $nombreCategoria]);

            foreach ($subcategorias as $nombreSubcategoria) {
                Subcategoria::firstOrCreate([
                    'categoria_id' => $categoria->id,
                    'nombre'       => $nombreSubcategoria,
                ]);
            }
        }
    }
}
