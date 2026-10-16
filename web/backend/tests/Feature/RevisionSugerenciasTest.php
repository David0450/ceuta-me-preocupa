<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\HistorialSugerencia;
use App\Models\Organismo;
use App\Models\PropuestaIa;
use App\Models\Subcategoria;
use App\Models\Sugerencia;
use App\Models\User;
use Database\Seeders\CategoriaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class RevisionSugerenciasTest extends TestCase
{
    use RefreshDatabase;

    private User $revisor;

    private Categoria $urbanismo;

    private Organismo $organismo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CategoriaSeeder::class);
        $this->revisor = User::factory()->revisor()->create();
        $this->urbanismo = Categoria::where('nombre', 'Urbanismo')->firstOrFail();
        $this->organismo = Organismo::create(['nombre' => 'DG de Urbanismo', 'tipo' => 'autonomico']);
    }

    private function crearSugerencia(array $atributos = []): Sugerencia
    {
        $sugerencia = Sugerencia::create(array_merge([
            'fecha_recepcion' => now(),
            'categoria_id' => $this->urbanismo->id,
            'estado' => 'recibido',
            'version_aviso_privacidad' => 'v1',
            'fecha_aceptacion' => now(),
        ], $atributos));

        $subcategoriaId = $this->urbanismo->subcategorias()->first()->id;
        $sugerencia->subcategorias()->sync([$subcategoriaId]);
        $sugerencia->update([
            'categoria_ciudadano_id' => $sugerencia->categoria_id,
            'subcategorias_ciudadano' => [$subcategoriaId],
        ]);
        $sugerencia->datoOriginal()->create([
            'texto_original' => 'Soy Ana, mi teléfono es 600123456. La acera de mi calle está rota.',
        ]);
        $sugerencia->contacto()->create([
            'canal' => 'ana@example.com',
            'consiente_respuesta' => true,
            'fecha_consentimiento' => now(),
        ]);

        return $sugerencia;
    }

    public function test_only_reviewers_can_access_the_panel(): void
    {
        $sugerencia = $this->crearSugerencia();
        $otro = User::factory()->create();

        $this->actingAs($otro)->get(route('admin.sugerencias.index'))->assertForbidden();
        $this->actingAs($otro)->get(route('admin.sugerencias.show', $sugerencia))->assertForbidden();

        $this->actingAs($this->revisor)->get(route('admin.sugerencias.index'))->assertOk();
        $this->actingAs($this->revisor)->get(route('admin.sugerencias.show', $sugerencia))
            ->assertOk()
            ->assertDontSee('600123456')
            ->assertDontSee('ana@example.com');
    }

    public function test_saving_anonymization_marks_privacy_reviewed_and_logs_it(): void
    {
        $sugerencia = $this->crearSugerencia();

        Livewire::actingAs($this->revisor)
            ->test('admin.revision-sugerencia', ['sugerencia' => $sugerencia])
            ->call('partirDelOriginal')
            ->assertSet('textoDesidentificado', 'Soy Ana, mi teléfono es [dato personal eliminado]. La acera de mi calle está rota.')
            ->set('textoDesidentificado', 'La acera de una calle del barrio está rota.')
            ->set('privacidadRevisada', true)
            ->call('guardarAnonimizacion')
            ->assertHasNoErrors();

        $sugerencia->refresh();
        $this->assertSame('La acera de una calle del barrio está rota.', $sugerencia->texto_desidentificado);
        $this->assertTrue($sugerencia->privacidadRevisada());
        $this->assertSame($this->revisor->id, $sugerencia->privacidad_revisada_por);
        $this->assertSame('en_revision', $sugerencia->estado);
        $this->assertDatabaseHas('historial_sugerencias', ['sugerencia_id' => $sugerencia->id, 'accion' => 'privacidad_revisada']);
    }

    public function test_privacy_cannot_be_marked_reviewed_without_text(): void
    {
        $sugerencia = $this->crearSugerencia();

        Livewire::actingAs($this->revisor)
            ->test('admin.revision-sugerencia', ['sugerencia' => $sugerencia])
            ->set('privacidadRevisada', true)
            ->call('guardarAnonimizacion')
            ->assertHasErrors('textoDesidentificado');

        $this->assertFalse($sugerencia->refresh()->privacidadRevisada());
    }

    public function test_subcategories_must_belong_to_the_chosen_category(): void
    {
        $sugerencia = $this->crearSugerencia();
        $ajena = Subcategoria::whereNot('categoria_id', $this->urbanismo->id)->first();

        Livewire::actingAs($this->revisor)
            ->test('admin.revision-sugerencia', ['sugerencia' => $sugerencia])
            ->set('subcategoriaIds', [$ajena->id])
            ->set('organismoId', $this->organismo->id)
            ->call('guardarClasificacion')
            ->assertHasErrors('subcategoriaIds.0');

        $this->assertFalse($sugerencia->refresh()->clasificacionRevisada());
    }

    public function test_classification_requires_organism_or_pending_competence(): void
    {
        $sugerencia = $this->crearSugerencia();

        Livewire::actingAs($this->revisor)
            ->test('admin.revision-sugerencia', ['sugerencia' => $sugerencia])
            ->call('guardarClasificacion')
            ->assertHasErrors('organismoId')
            ->set('competenciaPorConfirmar', true)
            ->call('guardarClasificacion')
            ->assertHasNoErrors();

        $this->assertTrue($sugerencia->refresh()->clasificacionRevisada());
    }

    public function test_reassigning_the_organism_requires_a_reason(): void
    {
        $sugerencia = $this->crearSugerencia(['organismo_id' => $this->organismo->id]);
        $otro = Organismo::create(['nombre' => 'DG de Medio Ambiente', 'tipo' => 'autonomico']);

        $componente = Livewire::actingAs($this->revisor)
            ->test('admin.revision-sugerencia', ['sugerencia' => $sugerencia])
            ->set('organismoId', $otro->id)
            ->call('guardarClasificacion')
            ->assertHasErrors('motivoReasignacion');

        $componente->set('motivoReasignacion', 'Se trata de limpieza viaria')
            ->call('guardarClasificacion')
            ->assertHasNoErrors();

        $this->assertSame($otro->id, $sugerencia->refresh()->organismo_id);
        $this->assertDatabaseHas('historial_sugerencias', [
            'sugerencia_id' => $sugerencia->id,
            'accion' => 'organismo_reasignado',
            'motivo' => 'Se trata de limpieza viaria',
        ]);
    }

    public function test_ai_proposal_prefills_text_and_is_evaluated_on_confirmation(): void
    {
        $sugerencia = $this->crearSugerencia();
        $obras = Subcategoria::where('categoria_id', $this->urbanismo->id)->where('nombre', 'Obras')->first();
        $movilidad = Categoria::where('nombre', 'Movilidad')->first();

        // Lo que haría la IA: un único INSERT en propuestas_ia.
        $propuesta = PropuestaIa::create([
            'sugerencia_id' => $sugerencia->id,
            'categoria_id' => $movilidad->id,
            'subcategoria_1_id' => $obras->id,
            'organismo_id' => $this->organismo->id,
            'nivel_urgencia' => 'ordinaria',
            'texto_desidentificado' => 'La acera de una calle está rota.',
            'propuesta_desidentificada' => 'Que la reparen.',
        ]);

        Livewire::actingAs($this->revisor)
            ->test('admin.revision-sugerencia', ['sugerencia' => $sugerencia])
            ->assertSet('textoDesidentificado', 'La acera de una calle está rota.')
            ->assertSet('textoDeIa', true)
            ->set('privacidadRevisada', true)
            ->call('guardarAnonimizacion')
            ->call('aplicarPropuestaIa')
            ->assertSet('categoriaId', $movilidad->id)
            ->assertSet('subcategoriaIds', [])
            ->set('categoriaId', $this->urbanismo->id)
            ->set('subcategoriaIds', [$obras->id])
            ->call('guardarClasificacion')
            ->assertHasNoErrors();

        $propuesta->refresh();
        $this->assertFalse($propuesta->texto_editado);
        $this->assertFalse($propuesta->categoria_correcta);
        $this->assertTrue($propuesta->subcategorias_correctas);
        $this->assertTrue($propuesta->organismo_correcto);
        $this->assertTrue($propuesta->urgencia_correcta);
        $this->assertSame($this->revisor->id, $propuesta->revisada_por);
        $this->assertSame('La acera de una calle está rota.', $sugerencia->refresh()->texto_desidentificado);
    }

    public function test_ai_can_insert_proposal_with_plain_sql(): void
    {
        $sugerencia = $this->crearSugerencia();

        DB::insert(
            'insert into propuestas_ia (sugerencia_id, categoria_id, nivel_urgencia, texto_desidentificado) values (?, ?, ?, ?)',
            [$sugerencia->id, $this->urbanismo->id, 'urgente', 'Texto de la IA']
        );

        $propuesta = $sugerencia->propuestaIa;
        $this->assertNotNull($propuesta->created_at);
        $this->assertSame('urgente', $propuesta->nivel_urgencia);
    }

    public function test_state_transitions_enforce_review_requirements(): void
    {
        $sugerencia = $this->crearSugerencia();

        $componente = Livewire::actingAs($this->revisor)
            ->test('admin.revision-sugerencia', ['sugerencia' => $sugerencia])
            ->call('cambiarEstado', 'clasificado')
            ->assertHasErrors('estado');

        $this->assertSame('recibido', $sugerencia->refresh()->estado);

        $componente
            ->set('textoDesidentificado', 'La acera está rota.')
            ->set('privacidadRevisada', true)
            ->call('guardarAnonimizacion')
            ->set('organismoId', $this->organismo->id)
            ->call('guardarClasificacion')
            ->call('cambiarEstado', 'derivado')
            ->assertHasNoErrors()
            ->call('cambiarEstado', 'cerrado')
            ->assertHasErrors('estado');

        $this->assertSame('derivado', $sugerencia->refresh()->estado);

        $componente->set('accionAdoptada', 'Remitida a la DG de Urbanismo.')
            ->call('guardarSeguimiento')
            ->call('cambiarEstado', 'cerrado')
            ->assertHasNoErrors();

        $this->assertSame('cerrado', $sugerencia->refresh()->estado);
    }

    public function test_revealing_contact_is_logged(): void
    {
        $sugerencia = $this->crearSugerencia();

        Livewire::actingAs($this->revisor)
            ->test('admin.revision-sugerencia', ['sugerencia' => $sugerencia])
            ->assertDontSee('ana@example.com')
            ->call('revelarContacto')
            ->assertSee('ana@example.com');

        $this->assertDatabaseHas('historial_sugerencias', [
            'sugerencia_id' => $sugerencia->id,
            'user_id' => $this->revisor->id,
            'accion' => 'contacto_consultado',
        ]);
    }

    public function test_improper_suggestion_goes_to_trash_and_can_be_restored(): void
    {
        $sugerencia = $this->crearSugerencia();

        Livewire::actingAs($this->revisor)
            ->test('admin.revision-sugerencia', ['sugerencia' => $sugerencia])
            ->call('eliminar')
            ->assertHasErrors('motivoEliminacion')
            ->set('motivoEliminacion', 'spam')
            ->call('eliminar')
            ->assertRedirect(route('admin.sugerencias.index'));

        $this->assertSoftDeleted($sugerencia);
        $this->assertSame('spam', Sugerencia::withTrashed()->find($sugerencia->id)->motivo_eliminacion);

        Livewire::actingAs($this->revisor)
            ->test('admin.sugerencias')
            ->call('restaurar', $sugerencia->id);

        $this->assertNotSoftDeleted($sugerencia);
    }

    public function test_permanent_deletion_removes_sensitive_data_but_keeps_audit_trail(): void
    {
        $sugerencia = $this->crearSugerencia();
        $sugerencia->delete();

        Livewire::actingAs($this->revisor)
            ->test('admin.sugerencias')
            ->call('eliminarDefinitivamente', $sugerencia->id);

        $this->assertDatabaseMissing('sugerencias', ['id' => $sugerencia->id]);
        $this->assertDatabaseMissing('datos_originales', ['sugerencia_id' => $sugerencia->id]);
        $this->assertDatabaseMissing('contactos', ['sugerencia_id' => $sugerencia->id]);
        $this->assertTrue(HistorialSugerencia::where('sugerencia_id', $sugerencia->id)
            ->where('accion', 'eliminada_definitivamente')->exists());
    }

    public function test_list_filters_pending_reviews_and_searches_only_deidentified_text(): void
    {
        $pendiente = $this->crearSugerencia();
        $revisada = $this->crearSugerencia([
            'texto_desidentificado' => 'Farolas apagadas en el paseo',
            'privacidad_revisada_at' => now(),
            'clasificacion_revisada_at' => now(),
        ]);

        Livewire::actingAs($this->revisor)
            ->test('admin.sugerencias')
            ->call('filtrarRevision', 'pendiente')
            ->assertSee(route('admin.sugerencias.show', $pendiente))
            ->assertDontSee(route('admin.sugerencias.show', $revisada))
            ->call('filtrarRevision', 'pendiente')
            ->set('buscar', 'Farolas')
            ->assertSee(route('admin.sugerencias.show', $revisada))
            ->assertDontSee(route('admin.sugerencias.show', $pendiente))
            ->set('buscar', 'acera')
            ->assertDontSee(route('admin.sugerencias.show', $pendiente));
    }

    public function test_urgency_proposed_by_ai_surfaces_until_classification_is_confirmed(): void
    {
        $antigua = $this->crearSugerencia(['fecha_recepcion' => now()->subDays(5)]);
        $reciente = $this->crearSugerencia(['fecha_recepcion' => now()]);
        PropuestaIa::create(['sugerencia_id' => $antigua->id, 'nivel_urgencia' => 'urgente']);

        $this->assertSame('urgente', $antigua->refresh()->urgenciaEfectiva());
        $this->assertTrue($antigua->urgenciaPorIa());

        $listado = Livewire::actingAs($this->revisor)->test('admin.sugerencias');
        $this->assertSame([$antigua->id, $reciente->id], $listado->get('sugerencias')->pluck('id')->all());
        $listado->call('filtrarRevision', 'urgente')
            ->assertSee(route('admin.sugerencias.show', $antigua))
            ->assertDontSee(route('admin.sugerencias.show', $reciente));

        // El revisor decide que no es urgente: a partir de ahí manda su decisión.
        Livewire::actingAs($this->revisor)
            ->test('admin.revision-sugerencia', ['sugerencia' => $antigua])
            ->set('organismoId', $this->organismo->id)
            ->set('nivelUrgencia', 'ordinaria')
            ->call('guardarClasificacion')
            ->assertHasNoErrors();

        $antigua->refresh()->load('propuestaIa');
        $this->assertSame('ordinaria', $antigua->urgenciaEfectiva());
        $this->assertFalse($antigua->urgenciaPorIa());
        $this->assertFalse($antigua->propuestaIa->urgencia_correcta);
    }

    public function test_citizen_choice_is_kept_after_reclassification(): void
    {
        $sugerencia = $this->crearSugerencia();
        $original = $sugerencia->subcategorias_ciudadano;
        $movilidad = Categoria::where('nombre', 'Movilidad')->first();
        $trafico = $movilidad->subcategorias()->where('nombre', 'Tráfico')->first();

        $componente = Livewire::actingAs($this->revisor)
            ->test('admin.revision-sugerencia', ['sugerencia' => $sugerencia])
            ->set('categoriaId', $movilidad->id)
            ->set('subcategoriaIds', [$trafico->id])
            ->set('organismoId', $this->organismo->id)
            ->call('guardarClasificacion')
            ->assertHasNoErrors();

        $sugerencia->refresh();
        $this->assertSame($movilidad->id, $sugerencia->categoria_id);
        $this->assertSame($this->urbanismo->id, $sugerencia->categoria_ciudadano_id);
        $this->assertSame($original, $sugerencia->subcategorias_ciudadano);

        $componente->assertSee('Elegido por el ciudadano')
            ->call('aplicarEleccionCiudadano')
            ->assertSet('categoriaId', $this->urbanismo->id)
            ->assertSet('subcategoriaIds', $original);
    }

    public function test_public_form_stores_the_citizen_choice(): void
    {
        $subcategoria = $this->urbanismo->subcategorias()->first();

        $this->post(route('sugerencias.store'), [
            'categoria_id' => $this->urbanismo->id,
            'subcategorias' => [$subcategoria->id],
            'descripcion' => 'Hay un bache enorme.',
            'acepta_privacidad' => '1',
            '_t' => encrypt(now()->subMinute()->timestamp),
        ])->assertRedirect();

        $sugerencia = Sugerencia::latest('id')->first();
        $this->assertSame($this->urbanismo->id, $sugerencia->categoria_ciudadano_id);
        $this->assertSame([$subcategoria->id], $sugerencia->subcategorias_ciudadano);
    }
}
