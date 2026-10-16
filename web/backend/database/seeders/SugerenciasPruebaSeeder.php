<?php

namespace Database\Seeders;

use App\Models\Categoria;
use App\Models\Organismo;
use App\Models\PropuestaIa;
use App\Models\Sugerencia;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Datos ficticios para probar a mano el panel de revisión.
 * NO se llama desde DatabaseSeeder. Uso (solo en local):
 *   php artisan db:seed --class=SugerenciasPruebaSeeder
 *
 * Cuenta revisora: revisor@prueba.test / password
 */
class SugerenciasPruebaSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('SugerenciasPruebaSeeder no debe ejecutarse en producción.');
        }

        $this->call(CategoriaSeeder::class);

        $revisor = User::firstOrCreate(
            ['email' => 'revisor@prueba.test'],
            ['name' => 'Revisor de prueba', 'password' => 'password'],
        );
        $revisor->forceFill(['es_revisor' => true, 'email_verified_at' => now()])->save();

        $organismos = collect([
            ['DG de Urbanismo y Patrimonio', 'autonomico'],
            ['DG de Transporte y Movilidad Urbana', 'autonomico'],
            ['DG de Medio Ambiente', 'autonomico'],
            ['DG de Servicios Sociales', 'autonomico'],
            ['Policía Local', 'autonomico'],
            ['Dirección Territorial de INGESA', 'estatal'],
            ['Dirección Provincial de Educación', 'estatal'],
            ['Delegación del Gobierno', 'estatal'],
            ['Universidad de Granada', 'universitario'],
        ])->mapWithKeys(fn (array $o) => [
            $o[0] => Organismo::firstOrCreate(['nombre' => $o[0]], ['tipo' => $o[1]]),
        ]);

        $casos = [
            // 1. Datos directos de todo tipo: el detector debe marcarlos todos.
            [
                'categoria' => 'Urbanismo', 'subcategorias' => ['Obras'],
                'texto' => 'Me llamo Ana Martín, vivo en la calle Real 25 y la acera de mi portal lleva tres meses levantada. '
                    .'Mi DNI es 12345678Z y mi móvil 600 123 456 por si necesitan comprobarlo.',
                'resultado' => 'Que reparen la acera. Escribidme a ana.martin@ejemplo.es',
                'zona' => 'Centro',
                'contacto' => 'ana.martin@ejemplo.es',
                // La IA acierta, pero se le escapa el nombre en el texto.
                'ia' => [
                    'categoria' => 'Urbanismo', 'subcategorias' => ['Obras'], 'organismo' => 'DG de Urbanismo y Patrimonio',
                    'texto' => 'Me llamo Ana Martín, vivo en la [dato personal eliminado] y la acera de mi portal lleva tres meses levantada. Mi DNI es [dato personal eliminado] y mi móvil [dato personal eliminado] por si necesitan comprobarlo.',
                    'propuesta' => 'Que reparen la acera. Escribidme a [dato personal eliminado]',
                ],
            ],
            // 2. Solo identificación indirecta: el detector NO debe encontrar nada.
            [
                'categoria' => 'Servicios Sociales', 'subcategorias' => ['Dependencia'],
                'texto' => 'Soy el único vecino en silla de ruedas del bloque amarillo junto al quiosco de Hadú. '
                    .'Desde que se averió el ascensor el martes pasado no puedo salir de casa.',
                'zona' => 'Hadú',
                // La IA se equivoca de categoría y deja la identificación indirecta.
                'ia' => [
                    'categoria' => 'Urbanismo', 'subcategorias' => ['Accesibilidad urbana'], 'organismo' => 'DG de Urbanismo y Patrimonio',
                    'texto' => 'Soy el único vecino en silla de ruedas del bloque amarillo junto al quiosco de Hadú. Desde que se averió el ascensor el martes pasado no puedo salir de casa.',
                ],
            ],
            // 3. Con teléfono y NIE. Solo la IA la marca como urgente: debe salir la primera del listado.
            [
                'categoria' => 'Seguridad', 'subcategorias' => ['Seguridad ciudadana'],
                'texto' => 'Hay un cable eléctrico suelto en el parque infantil de la barriada, los niños juegan al lado. '
                    .'Mi NIE es X1234567L y mi teléfono +34 956 123 456.',
                'zona' => 'Barriada Príncipe',
                'contacto' => '+34 956 123 456',
                'ia' => [
                    'categoria' => 'Seguridad', 'subcategorias' => ['Seguridad ciudadana'], 'organismo' => 'Policía Local', 'urgencia' => 'urgente',
                    'texto' => 'Hay un cable eléctrico suelto en el parque infantil de la barriada, los niños juegan al lado.',
                ],
            ],
            // 4. Ya tiene organismo asignado: para probar la reasignación con motivo.
            [
                'categoria' => 'Medioambiente', 'subcategorias' => ['Residuos', 'Limpieza'],
                'texto' => 'Los contenedores de la zona se desbordan todos los fines de semana.',
                'organismo' => 'DG de Urbanismo y Patrimonio',
                'zona' => 'Benítez',
                'organismo_ciudadano' => 'Servicios urbanos',
                // La IA propone otro organismo distinto al asignado.
                'ia' => [
                    'categoria' => 'Medioambiente', 'subcategorias' => ['Residuos', 'Limpieza'], 'organismo' => 'DG de Medio Ambiente',
                    'texto' => 'Los contenedores de la zona se desbordan todos los fines de semana.',
                ],
            ],
            // 5 y 6. Pareja de duplicados (campaña o mensajes casi iguales).
            [
                'categoria' => 'Movilidad', 'subcategorias' => ['Transporte público'],
                'texto' => 'El autobús de la línea 7 pasa cada 40 minutos por la mañana, es insuficiente.',
                'zona' => 'Los Rosales',
            ],
            [
                'categoria' => 'Movilidad', 'subcategorias' => ['Transporte público'],
                'texto' => 'La línea 7 de autobús tarda 40 minutos entre uno y otro por las mañanas.',
                'zona' => 'Los Rosales',
            ],
            // 7. Spam: para probar la papelera.
            [
                'categoria' => 'Economía', 'subcategorias' => ['Comercio'],
                'texto' => 'GANA DINERO DESDE CASA!!! entra en www.ejemplo-spam.test y consigue 5000€ al mes',
            ],
            // 8. Ya revisada y derivada: para probar el cierre (falta la acción adoptada).
            [
                'categoria' => 'Sanidad', 'subcategorias' => ['Atención sanitaria'],
                'texto' => 'Las esperas en urgencias del hospital superan las seis horas.',
                'desidentificado' => 'Las esperas en urgencias del hospital superan las seis horas.',
                'organismo' => 'Dirección Territorial de INGESA',
                'estado' => 'derivado',
                'revisada' => true,
            ],
        ];

        foreach ($casos as $i => $caso) {
            $categoria = Categoria::where('nombre', $caso['categoria'])->firstOrFail();

            $sugerencia = Sugerencia::create([
                'fecha_recepcion' => now()->subDays(count($casos) - $i)->subHours($i),
                'categoria_id' => $categoria->id,
                'organismo_id' => isset($caso['organismo']) ? $organismos[$caso['organismo']]->id : null,
                'estado' => $caso['estado'] ?? 'recibido',
                'nivel_urgencia' => $caso['urgencia'] ?? 'ordinaria',
                'texto_desidentificado' => $caso['desidentificado'] ?? null,
                'version_aviso_privacidad' => 'v1',
                'fecha_aceptacion' => now(),
                'privacidad_revisada_at' => ! empty($caso['revisada']) ? now() : null,
                'privacidad_revisada_por' => ! empty($caso['revisada']) ? $revisor->id : null,
                'clasificacion_revisada_at' => ! empty($caso['revisada']) ? now() : null,
                'clasificacion_revisada_por' => ! empty($caso['revisada']) ? $revisor->id : null,
            ]);

            $subcategoriaIds = $categoria->subcategorias()->whereIn('nombre', $caso['subcategorias'])->pluck('id');
            $sugerencia->subcategorias()->sync($subcategoriaIds);
            $sugerencia->update([
                'categoria_ciudadano_id' => $categoria->id,
                'subcategorias_ciudadano' => $subcategoriaIds->map(fn ($id) => (int) $id)->all(),
            ]);

            $sugerencia->datoOriginal()->create([
                'texto_original' => $caso['texto'],
                'resultado_esperado_original' => $caso['resultado'] ?? null,
                'zona_origen' => $caso['zona'] ?? null,
                'organismo_sugerido_ciudadano' => $caso['organismo_ciudadano'] ?? null,
            ]);

            if (isset($caso['contacto'])) {
                $sugerencia->contacto()->create([
                    'canal' => $caso['contacto'],
                    'consiente_respuesta' => true,
                    'fecha_consentimiento' => now(),
                ]);
            }

            if (isset($caso['ia'])) {
                $ia = $caso['ia'];
                $categoriaIa = Categoria::where('nombre', $ia['categoria'] ?? $caso['categoria'])->first();
                $subcategoriasIa = $categoriaIa?->subcategorias()->whereIn('nombre', $ia['subcategorias'] ?? [])->pluck('id')->all() ?? [];

                PropuestaIa::create([
                    'sugerencia_id' => $sugerencia->id,
                    'categoria_id' => $categoriaIa?->id,
                    'subcategoria_1_id' => $subcategoriasIa[0] ?? null,
                    'subcategoria_2_id' => $subcategoriasIa[1] ?? null,
                    'organismo_id' => isset($ia['organismo']) ? $organismos[$ia['organismo']]->id : null,
                    'nivel_urgencia' => $ia['urgencia'] ?? 'ordinaria',
                    'texto_desidentificado' => $ia['texto'] ?? null,
                    'propuesta_desidentificada' => $ia['propuesta'] ?? null,
                    'modelo_version' => 'qwen-prueba-v0',
                ]);
            }
        }

        $this->command?->info('Creadas '.count($casos).' sugerencias de prueba. Entra con revisor@prueba.test / password');
    }
}
