<?php

use App\Models\Categoria;
use App\Models\Organismo;
use App\Models\PropuestaIa;
use App\Models\Subcategoria;
use App\Models\Sugerencia;
use App\Support\DetectorDatosPersonales;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public Sugerencia $sugerencia;

    /* Anonimización */
    public string $textoDesidentificado = '';
    public string $resultadoDesidentificado = '';
    public bool $privacidadRevisada = false;
    public bool $mostrarOriginal = false;
    /** El cuadro se ha rellenado con el texto de la IA y aún no se ha guardado. */
    public bool $textoDeIa = false;

    /* Clasificación */
    public ?int $categoriaId = null;
    /** @var list<int> */
    public array $subcategoriaIds = [];
    public ?int $organismoId = null;
    public bool $competenciaPorConfirmar = false;
    public string $nivelUrgencia = 'ordinaria';
    public string $motivoReasignacion = '';

    /* Seguimiento */
    public string $accionAdoptada = '';
    public ?int $duplicadoDeId = null;

    /* Contacto */
    public bool $mostrarContacto = false;

    /* Eliminación */
    public string $motivoEliminacion = '';
    public string $detalleEliminacion = '';

    public function boot(): void
    {
        Gate::authorize('revisar-sugerencias');
    }

    public function mount(Sugerencia $sugerencia): void
    {
        $this->sugerencia = $sugerencia;
        $this->rellenarDesdeModelo();
    }

    private function rellenarDesdeModelo(): void
    {
        $s = $this->sugerencia->refresh();

        $this->textoDesidentificado = (string) $s->texto_desidentificado;
        $this->resultadoDesidentificado = (string) $s->resultado_esperado_desidentificado;
        $this->privacidadRevisada = $s->privacidadRevisada();
        $this->textoDeIa = false;

        // Si todavía no hay versión desidentificada guardada, se parte de la de la IA.
        $propuesta = $s->propuestaIa;
        if (blank($s->texto_desidentificado) && $propuesta && filled($propuesta->texto_desidentificado)) {
            $this->textoDesidentificado = (string) $propuesta->texto_desidentificado;
            $this->resultadoDesidentificado = (string) $propuesta->propuesta_desidentificada;
            $this->textoDeIa = true;
        }

        $this->categoriaId = (int) $s->categoria_id;
        $this->subcategoriaIds = $s->subcategorias()->pluck('subcategorias.id')->map(fn ($id) => (int) $id)->all();
        $this->organismoId = $s->organismo_id === null ? null : (int) $s->organismo_id;
        $this->competenciaPorConfirmar = (bool) $s->competencia_por_confirmar;
        $this->nivelUrgencia = $s->nivel_urgencia ?? 'ordinaria';
        $this->motivoReasignacion = '';

        $this->accionAdoptada = (string) $s->accion_adoptada;
        $this->duplicadoDeId = $s->duplicado_de_id === null ? null : (int) $s->duplicado_de_id;
    }

    public function updatedCategoriaId(): void
    {
        // Las subcategorías dependen de la categoría: al cambiarla se vacían.
        $this->subcategoriaIds = [];
    }

    /* ---------------------------------------------------------------- */
    /* Datos para la vista                                              */
    /* ---------------------------------------------------------------- */

    #[Computed]
    public function categorias()
    {
        return Categoria::orderBy('nombre')->get();
    }

    #[Computed]
    public function subcategoriasDisponibles()
    {
        return $this->categoriaId
            ? Subcategoria::where('categoria_id', $this->categoriaId)->orderBy('nombre')->get()
            : collect();
    }

    #[Computed]
    public function organismos()
    {
        return Organismo::orderBy('nombre')->get();
    }

    #[Computed]
    public function hallazgos(): array
    {
        return DetectorDatosPersonales::detectar($this->textoDesidentificado."\n".$this->resultadoDesidentificado);
    }

    #[Computed]
    public function propuesta(): ?PropuestaIa
    {
        return $this->sugerencia->propuestaIa()
            ->with(['categoria', 'subcategoria1', 'subcategoria2', 'organismo', 'revisor'])
            ->first();
    }

    /**
     * Lo que eligió la persona al enviar, comparado con el formulario.
     *
     * @return array{categoria: ?string, subcategorias: list<string>, organismo: ?string, coincide: array{categoria: bool, subcategorias: bool}}
     */
    #[Computed]
    public function eleccionCiudadano(): array
    {
        $s = $this->sugerencia;
        $subcategorias = $s->subcategoriasCiudadano();
        $finales = array_map('intval', $this->subcategoriaIds);
        $suyas = $subcategorias->pluck('id')->map(fn ($id) => (int) $id)->all();
        sort($finales);
        sort($suyas);

        return [
            'categoria' => $s->categoriaCiudadano?->nombre,
            'subcategorias' => $subcategorias->pluck('nombre')->all(),
            'organismo' => $s->datoOriginal?->organismo_sugerido_ciudadano,
            'coincide' => [
                'categoria' => $s->categoria_ciudadano_id !== null && (int) $s->categoria_ciudadano_id === $this->categoriaId,
                'subcategorias' => $suyas !== [] && $suyas === $finales,
            ],
        ];
    }

    /**
     * Compara la propuesta de la IA con lo que hay ahora en el formulario.
     *
     * @return array{categoria: bool, subcategorias: bool, organismo: bool, urgencia: bool}|null
     */
    #[Computed]
    public function coincidenciasIa(): ?array
    {
        $p = $this->propuesta;

        if (! $p) {
            return null;
        }

        $finales = array_map('intval', $this->subcategoriaIds);
        $propuestas = $p->subcategoriaIds();
        sort($finales);
        sort($propuestas);

        return [
            'categoria' => $p->categoria_id !== null && (int) $p->categoria_id === $this->categoriaId,
            'subcategorias' => $propuestas !== [] && $propuestas === $finales,
            'organismo' => $p->organismo_id !== null && (int) $p->organismo_id === $this->organismoId,
            'urgencia' => $p->nivel_urgencia !== null && $p->nivel_urgencia === $this->nivelUrgencia,
        ];
    }

    #[Computed]
    public function historial()
    {
        return $this->sugerencia->historial()->with('usuario')->limit(50)->get();
    }

    /* ---------------------------------------------------------------- */
    /* Anonimización                                                    */
    /* ---------------------------------------------------------------- */

    public function revelarOriginal(): void
    {
        if (! $this->mostrarOriginal) {
            $this->sugerencia->registrar('original_consultado');
            unset($this->historial);
        }
        $this->mostrarOriginal = ! $this->mostrarOriginal;
    }

    /** Copia el texto original como punto de partida, ya con las sustituciones automáticas. */
    public function partirDelOriginal(): void
    {
        $original = $this->sugerencia->datoOriginal;

        if (! $original) {
            return;
        }

        $this->textoDesidentificado = DetectorDatosPersonales::sustituir($original->texto_original);
        $this->resultadoDesidentificado = DetectorDatosPersonales::sustituir($original->resultado_esperado_original);
    }

    /** Vuelve a cargar en el cuadro el texto que propuso la IA. */
    public function usarTextoIa(): void
    {
        $p = $this->propuesta;

        if (! $p) {
            return;
        }

        $this->textoDesidentificado = (string) $p->texto_desidentificado;
        $this->resultadoDesidentificado = (string) $p->propuesta_desidentificada;
        $this->textoDeIa = true;
    }

    public function aplicarSustituciones(): void
    {
        $this->textoDesidentificado = DetectorDatosPersonales::sustituir($this->textoDesidentificado);
        $this->resultadoDesidentificado = DetectorDatosPersonales::sustituir($this->resultadoDesidentificado);
    }

    public function guardarAnonimizacion(): void
    {
        $this->validate([
            'textoDesidentificado' => [Rule::requiredIf($this->privacidadRevisada), 'nullable', 'string', 'max:2000'],
            'resultadoDesidentificado' => ['nullable', 'string', 'max:2000'],
        ], [
            'textoDesidentificado.required' => 'No puedes dar la privacidad por revisada sin texto desidentificado.',
        ]);

        $s = $this->sugerencia;
        $estabaRevisada = $s->privacidadRevisada();
        $textoCambiado = $s->texto_desidentificado !== ($this->textoDesidentificado ?: null)
            || $s->resultado_esperado_desidentificado !== ($this->resultadoDesidentificado ?: null);

        DB::transaction(function () use ($s, $estabaRevisada, $textoCambiado) {
            $s->fill([
                'texto_desidentificado' => $this->textoDesidentificado ?: null,
                'resultado_esperado_desidentificado' => $this->resultadoDesidentificado ?: null,
                'privacidad_revisada_at' => $this->privacidadRevisada ? ($estabaRevisada ? $s->privacidad_revisada_at : now()) : null,
                'privacidad_revisada_por' => $this->privacidadRevisada ? ($estabaRevisada ? $s->privacidad_revisada_por : auth()->id()) : null,
            ])->save();

            if ($textoCambiado) {
                $s->registrar('anonimizacion_guardada', [
                    'identificadores_detectados' => count($this->hallazgos),
                ]);
            }
            if ($this->privacidadRevisada && ! $estabaRevisada) {
                $s->registrar('privacidad_revisada', [
                    'identificadores_detectados' => count($this->hallazgos),
                ]);

                $this->propuesta?->update([
                    'texto_editado' => $this->normalizar($this->propuesta->texto_desidentificado) !== $this->normalizar($this->textoDesidentificado)
                        || $this->normalizar($this->propuesta->propuesta_desidentificada) !== $this->normalizar($this->resultadoDesidentificado),
                ]);
            }
            if (! $this->privacidadRevisada && $estabaRevisada) {
                $s->registrar('privacidad_reabierta');
            }

            $this->pasarAEnRevision();
        });

        $this->terminar('anonimizacion', 'Anonimización guardada.');
    }

    /* ---------------------------------------------------------------- */
    /* Clasificación                                                    */
    /* ---------------------------------------------------------------- */

    /** Vuelve a poner en el formulario la categoría y subcategorías del ciudadano (no guarda). */
    public function aplicarEleccionCiudadano(): void
    {
        $s = $this->sugerencia;

        if (! $s->categoria_ciudadano_id) {
            return;
        }

        $this->categoriaId = (int) $s->categoria_ciudadano_id;
        $this->subcategoriaIds = Subcategoria::whereIn('id', $s->subcategorias_ciudadano ?? [])
            ->where('categoria_id', $this->categoriaId)
            ->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->resetErrorBag();
    }

    /** Copia al formulario la clasificación propuesta por la IA (no guarda). */
    public function aplicarPropuestaIa(): void
    {
        $p = $this->propuesta;

        if (! $p) {
            return;
        }

        if ($p->categoria_id) {
            $this->categoriaId = (int) $p->categoria_id;
        }

        // Solo las subcategorías que pertenecen a la categoría elegida.
        $this->subcategoriaIds = Subcategoria::whereIn('id', $p->subcategoriaIds())
            ->where('categoria_id', $this->categoriaId)
            ->pluck('id')->map(fn ($id) => (int) $id)->all();

        if ($p->organismo_id) {
            $this->organismoId = (int) $p->organismo_id;
            $this->competenciaPorConfirmar = false;
        }

        if (array_key_exists((string) $p->nivel_urgencia, Sugerencia::URGENCIAS)) {
            $this->nivelUrgencia = $p->nivel_urgencia;
        }

        $this->resetErrorBag();
    }

    public function guardarClasificacion(): void
    {
        $s = $this->sugerencia;
        $this->subcategoriaIds = array_values(array_map('intval', $this->subcategoriaIds));
        $organismoAnterior = $s->organismo_id === null ? null : (int) $s->organismo_id;
        $reasigna = $organismoAnterior !== null && $organismoAnterior !== $this->organismoId;

        $this->validate([
            'categoriaId' => ['required', 'integer', 'exists:categorias,id'],
            'subcategoriaIds' => ['required', 'array', 'min:1', 'max:2'],
            'subcategoriaIds.*' => [
                'integer',
                'distinct',
                Rule::exists('subcategorias', 'id')->where('categoria_id', $this->categoriaId),
            ],
            'organismoId' => ['nullable', 'integer', 'exists:organismos,id'],
            'nivelUrgencia' => ['required', Rule::in(array_keys(Sugerencia::URGENCIAS))],
            'motivoReasignacion' => [Rule::requiredIf($reasigna), 'nullable', 'string', 'max:500'],
        ], [
            'subcategoriaIds.required' => 'Elige al menos una subcategoría.',
            'subcategoriaIds.min' => 'Elige al menos una subcategoría.',
            'subcategoriaIds.max' => 'Como máximo dos subcategorías.',
            'subcategoriaIds.*.exists' => 'Las subcategorías deben pertenecer a la categoría elegida.',
            'motivoReasignacion.required' => 'El protocolo exige registrar el motivo al reasignar el organismo.',
        ]);

        if ($this->organismoId === null && ! $this->competenciaPorConfirmar) {
            $this->addError('organismoId', 'Elige un organismo o marca «competencia por confirmar».');

            return;
        }

        DB::transaction(function () use ($s, $organismoAnterior, $reasigna) {
            $antes = [
                'categoria' => $s->categoria?->nombre,
                'subcategorias' => $s->subcategorias()->pluck('nombre')->sort()->values()->all(),
                'organismo' => $s->organismo?->nombre,
                'competencia_por_confirmar' => (bool) $s->competencia_por_confirmar,
                'nivel_urgencia' => $s->nivel_urgencia,
            ];

            $s->fill([
                'categoria_id' => $this->categoriaId,
                'organismo_id' => $this->organismoId,
                'competencia_por_confirmar' => $this->competenciaPorConfirmar,
                'nivel_urgencia' => $this->nivelUrgencia,
                'clasificacion_revisada_at' => now(),
                'clasificacion_revisada_por' => auth()->id(),
            ])->save();
            $s->subcategorias()->sync($this->subcategoriaIds);
            $s->load(['categoria', 'organismo', 'subcategorias']);

            $despues = [
                'categoria' => $s->categoria?->nombre,
                'subcategorias' => $s->subcategorias->pluck('nombre')->sort()->values()->all(),
                'organismo' => $s->organismo?->nombre,
                'competencia_por_confirmar' => (bool) $s->competencia_por_confirmar,
                'nivel_urgencia' => $s->nivel_urgencia,
            ];

            $cambios = [];
            foreach ($despues as $campo => $valor) {
                if ($antes[$campo] !== $valor) {
                    $cambios[$campo] = [$antes[$campo], $valor];
                }
            }

            $s->registrar('clasificacion_confirmada', $cambios);
            if ($reasigna) {
                $s->registrar('organismo_reasignado', ['organismo' => [$antes['organismo'], $despues['organismo']]], $this->motivoReasignacion);
            }

            $this->evaluarPropuestaIa();
            $this->pasarAEnRevision();
        });

        $this->terminar('clasificacion', 'Clasificación confirmada.');
    }

    /**
     * Anota en la propuesta de la IA en qué acertó respecto a la clasificación
     * confirmada por la persona revisora. Son las métricas para evaluar el modelo.
     */
    private function evaluarPropuestaIa(): void
    {
        unset($this->coincidenciasIa);
        $coincidencias = $this->coincidenciasIa;

        if ($coincidencias === null) {
            return;
        }

        $this->propuesta->update([
            'revisada_por' => auth()->id(),
            'revisada_at' => now(),
            'categoria_correcta' => $coincidencias['categoria'],
            'subcategorias_correctas' => $coincidencias['subcategorias'],
            'organismo_correcto' => $coincidencias['organismo'],
            'urgencia_correcta' => $coincidencias['urgencia'],
        ]);
    }

    private function normalizar(?string $texto): string
    {
        return trim(preg_replace('/\s+/u', ' ', (string) $texto));
    }

    /* ---------------------------------------------------------------- */
    /* Estado y seguimiento                                             */
    /* ---------------------------------------------------------------- */

    public function cambiarEstado(string $estado): void
    {
        if (! array_key_exists($estado, Sugerencia::ESTADOS) || $estado === $this->sugerencia->estado) {
            return;
        }

        $pendientes = $this->sugerencia->requisitosPendientesPara($estado);
        if ($pendientes !== []) {
            $this->addError('estado', implode(' ', $pendientes));

            return;
        }

        $anterior = $this->sugerencia->estado;
        $this->sugerencia->update(['estado' => $estado]);
        $this->sugerencia->registrar('estado_cambiado', ['estado' => [$anterior, $estado]]);

        $this->terminar('estado', 'Estado: '.Sugerencia::ESTADOS[$estado].'.');
    }

    public function guardarSeguimiento(): void
    {
        $this->validate([
            'accionAdoptada' => ['nullable', 'string', 'max:2000'],
            'duplicadoDeId' => [
                'nullable',
                'integer',
                Rule::notIn([$this->sugerencia->id]),
                Rule::exists('sugerencias', 'id'),
            ],
        ], [
            'duplicadoDeId.not_in' => 'Una sugerencia no puede ser duplicado de sí misma.',
            'duplicadoDeId.exists' => 'No existe ninguna sugerencia con ese número.',
        ]);

        $s = $this->sugerencia;
        $cambios = [];

        if (($s->duplicado_de_id === null ? null : (int) $s->duplicado_de_id) !== $this->duplicadoDeId) {
            $cambios['duplicado_de'] = [$s->duplicado_de_id, $this->duplicadoDeId];
        }
        if ((string) $s->accion_adoptada !== $this->accionAdoptada) {
            $cambios['accion_adoptada'] = 'modificada';
        }

        if ($cambios === []) {
            $this->terminar('seguimiento', 'Sin cambios.');

            return;
        }

        $s->update([
            'accion_adoptada' => $this->accionAdoptada ?: null,
            'duplicado_de_id' => $this->duplicadoDeId,
        ]);
        $s->registrar('seguimiento_guardado', $cambios);

        $this->terminar('seguimiento', 'Seguimiento guardado.');
    }

    /* ---------------------------------------------------------------- */
    /* Contacto (dato cifrado, acceso registrado)                       */
    /* ---------------------------------------------------------------- */

    public function revelarContacto(): void
    {
        if (! $this->sugerencia->contacto) {
            return;
        }

        if (! $this->mostrarContacto) {
            $this->sugerencia->registrar('contacto_consultado');
            unset($this->historial);
        }
        $this->mostrarContacto = ! $this->mostrarContacto;
    }

    public function marcarRespondido(): void
    {
        $contacto = $this->sugerencia->contacto;

        if (! $contacto || $contacto->respondido) {
            return;
        }

        $contacto->update(['respondido' => true, 'fecha_respuesta' => now()]);
        $this->sugerencia->registrar('contacto_respondido');

        $this->terminar('contacto', 'Marcado como respondido.');
    }

    /* ---------------------------------------------------------------- */
    /* Papelera                                                         */
    /* ---------------------------------------------------------------- */

    public function eliminar(): void
    {
        $this->validate([
            'motivoEliminacion' => ['required', Rule::in(array_keys(Sugerencia::MOTIVOS_ELIMINACION))],
            'detalleEliminacion' => [Rule::requiredIf($this->motivoEliminacion === 'otro'), 'nullable', 'string', 'max:500'],
        ], [
            'motivoEliminacion.required' => 'Indica el motivo de la eliminación.',
            'detalleEliminacion.required' => 'Explica brevemente el motivo.',
        ]);

        $s = $this->sugerencia;

        DB::transaction(function () use ($s) {
            $s->update([
                'motivo_eliminacion' => $this->motivoEliminacion,
                'eliminada_por' => auth()->id(),
            ]);
            $s->delete();
            $s->registrar('eliminada', ['motivo' => $this->motivoEliminacion], $this->detalleEliminacion ?: null);
        });

        session()->flash('estado_panel', "Sugerencia #{$s->id} enviada a la papelera.");
        $this->redirectRoute('admin.sugerencias.index');
    }

    public function restaurar(): void
    {
        $s = $this->sugerencia;

        if (! $s->trashed()) {
            return;
        }

        $s->restore();
        $s->update(['motivo_eliminacion' => null, 'eliminada_por' => null]);
        $s->registrar('restaurada');

        $this->terminar('papelera', 'Sugerencia restaurada.');
    }

    /* ---------------------------------------------------------------- */

    /** La primera acción de revisión saca la sugerencia de «recibido». */
    private function pasarAEnRevision(): void
    {
        if ($this->sugerencia->estado === 'recibido') {
            $this->sugerencia->update(['estado' => 'en_revision']);
            $this->sugerencia->registrar('estado_cambiado', ['estado' => ['recibido', 'en_revision']]);
        }
    }

    private function terminar(string $seccion, string $mensaje): void
    {
        $this->rellenarDesdeModelo();
        unset($this->historial, $this->propuesta, $this->coincidenciasIa, $this->eleccionCiudadano);
        $this->resetErrorBag();
        session()->flash("ok_{$seccion}", $mensaje);
    }
}; ?>

@php
    $s = $sugerencia;
    $original = $s->datoOriginal;
    $contacto = $s->contacto;
    $estadoClases = [
        'recibido' => 'bg-gray-100 text-gray-700',
        'en_revision' => 'bg-amber-100 text-amber-800',
        'clasificado' => 'bg-sky-100 text-sky-800',
        'derivado' => 'bg-indigo-100 text-indigo-800',
        'cerrado' => 'bg-green-100 text-green-800',
    ];
    $ok = fn (string $seccion) => session("ok_{$seccion}");
@endphp

<div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

    {{-- ============ Papelera ============ --}}
    @if ($s->trashed())
        <div class="bg-red-50 border border-red-200 sm:rounded-lg p-4 flex flex-wrap items-center justify-between gap-3 text-sm">
            <div class="text-red-800">
                <strong>En la papelera</strong> desde el {{ $s->deleted_at->format('d/m/Y H:i') }}
                @if ($s->eliminadaPor) por {{ $s->eliminadaPor->name }} @endif
                — {{ \App\Models\Sugerencia::MOTIVOS_ELIMINACION[$s->motivo_eliminacion] ?? $s->motivo_eliminacion }}.
            </div>
            <button wire:click="restaurar" class="px-3 py-1.5 rounded-md bg-white border border-red-300 text-red-700 hover:bg-red-100">
                Restaurar
            </button>
        </div>
    @endif

    {{-- ============ Cabecera: estado y checklist ============ --}}
    <div class="bg-white shadow-sm sm:rounded-lg p-6 text-gray-900">
        <div class="flex flex-wrap items-start justify-between gap-6">
            <dl class="grid grid-cols-2 sm:grid-cols-4 gap-x-8 gap-y-3 text-sm">
                <div><dt class="text-gray-500">Recibida</dt><dd>{{ $s->fecha_recepcion?->format('d/m/Y H:i') }}</dd></div>
                <div><dt class="text-gray-500">Zona</dt><dd>{{ $original?->zona_origen ?: '—' }}</dd></div>
                <div>
                    <dt class="text-gray-500">Urgencia</dt>
                    @php $urgencia = $s->urgenciaEfectiva(); @endphp
                    <dd class="{{ $urgencia === 'urgente' ? 'text-red-600 font-semibold' : ($urgencia === 'prioritaria' ? 'text-orange-600 font-semibold' : '') }}">
                        {{ \App\Models\Sugerencia::URGENCIAS[$urgencia] ?? $urgencia }}
                        @if ($s->urgenciaPorIa())<span class="block text-xs font-normal text-violet-700">Según la IA, sin confirmar</span>@endif
                    </dd>
                </div>
                <div><dt class="text-gray-500">Aviso de privacidad</dt><dd>{{ $s->version_aviso_privacidad }}</dd></div>
                <div>
                    <dt class="text-gray-500">IA</dt>
                    <dd>
                        @if ($this->propuesta)
                            Procesada {{ $this->propuesta->created_at?->format('d/m H:i') }}
                            @if ($this->propuesta->modelo_version)<span class="block text-xs text-gray-400">{{ $this->propuesta->modelo_version }}</span>@endif
                        @else
                            <span class="text-gray-400">Sin procesar</span>
                        @endif
                    </dd>
                </div>
            </dl>

            <ul class="text-sm space-y-1">
                @foreach ([
                    ['Texto desidentificado', filled($s->texto_desidentificado)],
                    ['Privacidad revisada', $s->privacidadRevisada()],
                    ['Clasificación confirmada', $s->clasificacionRevisada()],
                    ['Organismo asignado', $s->organismo_id && ! $s->competencia_por_confirmar],
                ] as [$etiqueta, $hecho])
                    <li class="flex items-center gap-2 {{ $hecho ? 'text-green-700' : 'text-gray-400' }}">
                        <span class="inline-flex h-4 w-4 items-center justify-center rounded-full text-[10px] {{ $hecho ? 'bg-green-100' : 'bg-gray-100' }}">{{ $hecho ? '✓' : '·' }}</span>
                        {{ $etiqueta }}
                    </li>
                @endforeach
            </ul>
        </div>

        {{-- Flujo de estados --}}
        <div class="mt-6 pt-5 border-t border-gray-100">
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-xs font-medium text-gray-500 mr-1">Estado</span>
                @foreach (\App\Models\Sugerencia::ESTADOS as $valor => $etiqueta)
                    @php
                        $actual = $s->estado === $valor;
                        $pendientes = $actual ? [] : $s->requisitosPendientesPara($valor);
                    @endphp
                    <button type="button"
                            wire:click="cambiarEstado('{{ $valor }}')"
                            @disabled($actual || $pendientes || $s->trashed())
                            title="{{ $pendientes ? implode(' ', $pendientes) : '' }}"
                            class="px-3 py-1 rounded-full text-xs font-medium border transition
                                {{ $actual ? ($estadoClases[$valor].' border-transparent ring-2 ring-offset-1 ring-gray-300') : 'border-gray-200 text-gray-600 hover:bg-gray-50' }}
                                disabled:cursor-not-allowed {{ ! $actual && $pendientes ? 'opacity-40' : '' }}">
                        {{ $etiqueta }}
                    </button>
                    @if (! $loop->last)<span class="text-gray-300">›</span>@endif
                @endforeach

                @if ($ok('estado'))<span class="text-sm text-green-600 ml-2">{{ $ok('estado') }}</span>@endif
            </div>
            @error('estado')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
            <p class="mt-2 text-xs text-gray-400">Los estados atenuados tienen requisitos pendientes (pasa el ratón por encima para verlos).</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">

            {{-- ============ 1. Anonimización ============ --}}
            <section class="bg-white shadow-sm sm:rounded-lg p-6 text-gray-900">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-semibold text-lg">1. Revisión de privacidad</h3>
                    @if ($s->privacidadRevisada())
                        <span class="text-xs text-green-700">Revisada por {{ $s->revisorPrivacidad?->name ?? '—' }} · {{ $s->privacidad_revisada_at->format('d/m/Y H:i') }}</span>
                    @endif
                </div>

                {{-- Original (nivel A, acceso registrado) --}}
                <div class="mb-5 rounded-md border border-amber-200 bg-amber-50 p-4">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-sm font-medium text-amber-900">Texto original</p>
                            <p class="text-xs text-amber-700">Contiene datos sin desidentificar. Cada consulta queda registrada.</p>
                        </div>
                        <button type="button" wire:click="revelarOriginal"
                                class="px-3 py-1.5 rounded-md text-sm border border-amber-300 bg-white text-amber-800 hover:bg-amber-100">
                            {{ $mostrarOriginal ? 'Ocultar' : 'Mostrar' }}
                        </button>
                    </div>

                    @if ($mostrarOriginal)
                        @if ($original)
                            <div class="mt-4 space-y-3 text-sm text-gray-900">
                                <p class="whitespace-pre-wrap leading-relaxed">{!! \App\Support\DetectorDatosPersonales::resaltar($original->texto_original) !!}</p>
                                @if ($original->resultado_esperado_original)
                                    <div>
                                        <span class="text-xs text-gray-500">Resultado esperado</span>
                                        <p class="whitespace-pre-wrap leading-relaxed">{!! \App\Support\DetectorDatosPersonales::resaltar($original->resultado_esperado_original) !!}</p>
                                    </div>
                                @endif
                                <p class="text-xs text-gray-500">En rojo, identificadores detectados automáticamente.</p>
                            </div>
                        @else
                            <p class="mt-3 text-sm text-gray-500 italic">Sin datos originales.</p>
                        @endif
                    @endif
                </div>

                {{-- Versión desidentificada (nivel B) --}}
                <div class="space-y-4">
                    @if ($textoDeIa && ! $s->privacidadRevisada())
                        <p class="rounded-md bg-violet-50 border border-violet-200 px-3 py-2 text-sm text-violet-800">
                            Texto propuesto por la IA. Revísalo y guárdalo: todavía no se ha guardado.
                        </p>
                    @endif

                    <div class="flex flex-wrap gap-2 text-sm">
                        @if ($this->propuesta && filled($this->propuesta->texto_desidentificado))
                            <button type="button" wire:click="usarTextoIa"
                                    wire:confirm="Se sustituirá el texto del cuadro por el que propuso la IA. ¿Continuar?"
                                    class="px-3 py-1.5 rounded-md border border-violet-300 text-violet-800 hover:bg-violet-50">
                                Usar texto de la IA
                            </button>
                        @endif
                        @if ($original)
                            <button type="button" wire:click="partirDelOriginal"
                                    @if (filled($textoDesidentificado)) wire:confirm="Se sustituirá el texto desidentificado actual por el original con las sustituciones automáticas. ¿Continuar?" @endif
                                    class="px-3 py-1.5 rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50">
                                Partir del original
                            </button>
                        @endif
                        <button type="button" wire:click="aplicarSustituciones"
                                class="px-3 py-1.5 rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50">
                            Sustituir identificadores detectados
                        </button>
                    </div>

                    <label class="block">
                        <span class="block text-sm font-medium text-gray-700 mb-1">Texto desidentificado</span>
                        <textarea wire:model.live.debounce.500ms="textoDesidentificado" rows="6"
                                  class="w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                        @error('textoDesidentificado')<span class="text-sm text-red-600">{{ $message }}</span>@enderror
                    </label>

                    <label class="block">
                        <span class="block text-sm font-medium text-gray-700 mb-1">Resultado esperado desidentificado <span class="text-gray-400 font-normal">(opcional)</span></span>
                        <textarea wire:model.live.debounce.500ms="resultadoDesidentificado" rows="3"
                                  class="w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                        @error('resultadoDesidentificado')<span class="text-sm text-red-600">{{ $message }}</span>@enderror
                    </label>

                    @if (count($this->hallazgos))
                        <div class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800">
                            <p class="font-medium">Posibles datos personales en la versión desidentificada:</p>
                            <ul class="mt-1 list-disc list-inside">
                                @foreach ($this->hallazgos as $h)
                                    <li>{{ $h['etiqueta'] }}: <code class="text-xs">{{ $h['valor'] }}</code></li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="rounded-md bg-gray-50 p-3 text-xs text-gray-600 leading-relaxed">
                        La detección automática no encuentra <strong>nombres propios</strong> ni identificación <strong>indirecta</strong>
                        (una calle concreta, una fecha, un hecho poco frecuente o un relato muy detallado). Si existe ese riesgo,
                        generaliza la zona o la fecha o resume el relato. Usa el marcador <code>{{ \App\Support\DetectorDatosPersonales::MARCADOR }}</code>.
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-3 pt-2">
                        <label class="inline-flex items-center gap-2 text-sm">
                            <input type="checkbox" wire:model="privacidadRevisada" class="rounded border-gray-300 text-indigo-600">
                            He revisado el texto y no permite identificar a nadie
                        </label>
                        <div class="flex items-center gap-3">
                            @if ($ok('anonimizacion'))<span class="text-sm text-green-600">{{ $ok('anonimizacion') }}</span>@endif
                            <button wire:click="guardarAnonimizacion" wire:loading.attr="disabled" wire:target="guardarAnonimizacion"
                                    class="px-4 py-2 bg-indigo-600 text-white text-sm rounded-md hover:bg-indigo-700 disabled:opacity-50">
                                Guardar anonimización
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            {{-- ============ 2. Clasificación ============ --}}
            <section class="bg-white shadow-sm sm:rounded-lg p-6 text-gray-900">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-semibold text-lg">2. Clasificación</h3>
                    @if ($s->clasificacionRevisada())
                        <span class="text-xs text-green-700">Confirmada por {{ $s->revisorClasificacion?->name ?? '—' }} · {{ $s->clasificacion_revisada_at->format('d/m/Y H:i') }}</span>
                    @endif
                </div>

                {{-- Puntos de partida: lo que eligió el ciudadano y lo que propone la IA --}}
                @php
                    $p = $this->propuesta;
                    $ciudadano = $this->eleccionCiudadano;
                    $ia = $this->coincidenciasIa;
                    $filas = [
                        'categoria' => ['Categoría', $ciudadano['categoria'], $p?->categoria?->nombre],
                        'subcategorias' => ['Subcategorías', implode(', ', $ciudadano['subcategorias']), $p ? implode(', ', $p->nombresSubcategorias()) : null],
                        'organismo' => ['Organismo', $ciudadano['organismo'], $p?->organismo?->nombre],
                        'urgencia' => ['Urgencia', null, $p ? (\App\Models\Sugerencia::URGENCIAS[$p->nivel_urgencia] ?? $p->nivel_urgencia) : null],
                    ];
                    $celda = fn (?string $valor, bool $coincide) => $coincide ? 'text-green-700' : ($valor ? 'text-gray-900' : 'text-gray-400');
                @endphp
                <div class="mb-5 rounded-md border border-gray-200 overflow-hidden text-sm">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-gray-50 text-left">
                                <th class="py-2 px-3 w-32"></th>
                                <th class="py-2 px-3">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <span class="font-medium text-gray-800">Elegido por el ciudadano</span>
                                        @if ($s->categoria_ciudadano_id)
                                            <button type="button" wire:click="aplicarEleccionCiudadano"
                                                    class="px-2 py-0.5 rounded border border-gray-300 bg-white text-xs text-gray-700 hover:bg-gray-100">
                                                Usar
                                            </button>
                                        @endif
                                    </div>
                                </th>
                                <th class="py-2 px-3 bg-violet-50">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <span class="font-medium text-violet-900">Propuesta de la IA</span>
                                        @if ($p)
                                            <button type="button" wire:click="aplicarPropuestaIa"
                                                    class="px-2 py-0.5 rounded border border-violet-300 bg-white text-xs text-violet-800 hover:bg-violet-100">
                                                Usar
                                            </button>
                                        @endif
                                    </div>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($filas as $campo => [$titulo, $valorCiudadano, $valorIa])
                                @php
                                    $coincideCiudadano = $ciudadano['coincide'][$campo] ?? false;
                                    $coincideIa = $ia[$campo] ?? false;
                                @endphp
                                <tr class="border-t border-gray-100 align-top">
                                    <td class="py-2 px-3 text-xs text-gray-500">{{ $titulo }}</td>
                                    <td class="py-2 px-3 {{ $celda($valorCiudadano, $coincideCiudadano) }}">
                                        @if ($campo === 'organismo' && $valorCiudadano)
                                            «{{ $valorCiudadano }}» <span class="text-xs text-gray-400">(texto libre)</span>
                                        @else
                                            {{ $valorCiudadano ?: '—' }}
                                        @endif
                                        @if ($coincideCiudadano)<span title="Coincide con el formulario">✓</span>@endif
                                    </td>
                                    <td class="py-2 px-3 bg-violet-50/40 {{ $celda($valorIa, $coincideIa) }}">
                                        @if ($p)
                                            {{ $valorIa ?: '—' }}
                                            @if ($coincideIa)<span title="Coincide con el formulario">✓</span>@endif
                                        @elseif ($loop->first)
                                            <span class="text-gray-400">Sin procesar todavía</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <p class="px-3 py-2 border-t border-gray-100 bg-gray-50 text-xs text-gray-500">
                        ✓ = coincide con lo que hay ahora en el formulario. «Usar» solo rellena el formulario; no se guarda hasta confirmar.
                        @if ($p?->revisada_at)
                            Propuesta de la IA evaluada por {{ $p->revisor?->name ?? '—' }} el {{ $p->revisada_at->format('d/m/Y H:i') }}.
                        @endif
                    </p>
                </div>

                <div class="grid sm:grid-cols-2 gap-4 text-sm">
                    <label class="block">
                        <span class="block text-xs font-medium text-gray-500 mb-1">Categoría principal</span>
                        <select wire:model.live="categoriaId" class="w-full rounded-md border-gray-300 text-sm">
                            <option value="">Elige…</option>
                            @foreach ($this->categorias as $c)
                                <option value="{{ $c->id }}">{{ $c->nombre }}</option>
                            @endforeach
                        </select>
                        @error('categoriaId')<span class="text-red-600">{{ $message }}</span>@enderror
                    </label>

                    <label class="block">
                        <span class="block text-xs font-medium text-gray-500 mb-1">Nivel de urgencia</span>
                        <select wire:model.live="nivelUrgencia" class="w-full rounded-md border-gray-300 text-sm">
                            @foreach (\App\Models\Sugerencia::URGENCIAS as $valor => $etiqueta)
                                <option value="{{ $valor }}">{{ $etiqueta }}</option>
                            @endforeach
                        </select>
                    </label>

                    <fieldset class="sm:col-span-2">
                        <legend class="block text-xs font-medium text-gray-500 mb-2">Subcategorías <span class="font-normal">(1 o 2)</span></legend>
                        <div class="flex flex-wrap gap-2">
                            @forelse ($this->subcategoriasDisponibles as $sub)
                                @php $marcada = in_array($sub->id, $subcategoriaIds); @endphp
                                <label wire:key="sub-{{ $sub->id }}"
                                       class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full border cursor-pointer
                                              {{ $marcada ? 'bg-indigo-50 border-indigo-300 text-indigo-800' : 'border-gray-200 text-gray-600 hover:bg-gray-50' }}
                                              {{ ! $marcada && count($subcategoriaIds) >= 2 ? 'opacity-40 cursor-not-allowed' : '' }}">
                                    <input type="checkbox" value="{{ $sub->id }}" wire:model.live="subcategoriaIds" class="sr-only"
                                           @disabled(! $marcada && count($subcategoriaIds) >= 2)>
                                    {{ $sub->nombre }}
                                </label>
                            @empty
                                <span class="text-gray-400">Elige primero una categoría.</span>
                            @endforelse
                        </div>
                        @error('subcategoriaIds')<span class="text-red-600">{{ $message }}</span>@enderror
                        @error('subcategoriaIds.*')<span class="text-red-600">{{ $message }}</span>@enderror
                    </fieldset>

                    <label class="block sm:col-span-2">
                        <span class="block text-xs font-medium text-gray-500 mb-1">Organismo de acción</span>
                        <select wire:model.live="organismoId" class="w-full rounded-md border-gray-300 text-sm">
                            <option value="">Sin asignar</option>
                            @foreach ($this->organismos as $o)
                                <option value="{{ $o->id }}">{{ $o->nombre }}</option>
                            @endforeach
                        </select>
                        @error('organismoId')<span class="text-red-600">{{ $message }}</span>@enderror
                    </label>

                    <label class="inline-flex items-center gap-2 sm:col-span-2">
                        <input type="checkbox" wire:model="competenciaPorConfirmar" class="rounded border-gray-300 text-indigo-600">
                        Competencia por confirmar <span class="text-gray-400">(información insuficiente o varios niveles administrativos)</span>
                    </label>

                    @if ($s->organismo_id && $organismoId !== (int) $s->organismo_id)
                        <label class="block sm:col-span-2">
                            <span class="block text-xs font-medium text-gray-500 mb-1">Motivo de la reasignación <span class="text-red-500">*</span></span>
                            <input type="text" wire:model="motivoReasignacion" maxlength="500"
                                   class="w-full rounded-md border-gray-300 text-sm"
                                   placeholder="Por qué cambia de {{ $s->organismo?->nombre }} a otro organismo">
                            @error('motivoReasignacion')<span class="text-red-600">{{ $message }}</span>@enderror
                        </label>
                    @endif
                </div>

                <div class="mt-5 flex items-center justify-end gap-3">
                    @if ($ok('clasificacion'))<span class="text-sm text-green-600">{{ $ok('clasificacion') }}</span>@endif
                    <button wire:click="guardarClasificacion" wire:loading.attr="disabled" wire:target="guardarClasificacion"
                            class="px-4 py-2 bg-indigo-600 text-white text-sm rounded-md hover:bg-indigo-700 disabled:opacity-50">
                        Confirmar clasificación
                    </button>
                </div>
            </section>

            {{-- ============ 3. Seguimiento ============ --}}
            <section class="bg-white shadow-sm sm:rounded-lg p-6 text-gray-900">
                <h3 class="font-semibold text-lg mb-4">3. Seguimiento</h3>

                <div class="grid sm:grid-cols-3 gap-4 text-sm">
                    <label class="block sm:col-span-2">
                        <span class="block text-xs font-medium text-gray-500 mb-1">Acción adoptada <span class="font-normal">(obligatoria para cerrar)</span></span>
                        <textarea wire:model="accionAdoptada" rows="3" class="w-full rounded-md border-gray-300 text-sm"></textarea>
                        @error('accionAdoptada')<span class="text-red-600">{{ $message }}</span>@enderror
                    </label>
                    <label class="block">
                        <span class="block text-xs font-medium text-gray-500 mb-1">Duplicado de la sugerencia nº</span>
                        <input type="number" min="1" wire:model="duplicadoDeId" class="w-full rounded-md border-gray-300 text-sm" placeholder="—">
                        @error('duplicadoDeId')<span class="text-red-600">{{ $message }}</span>@enderror
                        @if ($s->duplicadoDe)
                            <a href="{{ route('admin.sugerencias.show', $s->duplicadoDe) }}" class="block mt-1 text-xs text-indigo-600 hover:underline">Ver #{{ $s->duplicadoDe->id }}</a>
                        @endif
                        <span class="block mt-1 text-xs text-gray-400">Los duplicados se marcan, no se borran: cuentan como envíos.</span>
                    </label>
                </div>

                @if ($s->duplicados()->exists())
                    <p class="mt-3 text-sm text-gray-600">
                        Otras sugerencias marcadas como duplicado de esta:
                        @foreach ($s->duplicados()->pluck('id') as $idDuplicado)
                            <a href="{{ route('admin.sugerencias.show', $idDuplicado) }}" class="text-indigo-600 hover:underline">#{{ $idDuplicado }}</a>@if (! $loop->last), @endif
                        @endforeach
                    </p>
                @endif

                <div class="mt-4 flex items-center justify-end gap-3">
                    @if ($ok('seguimiento'))<span class="text-sm text-green-600">{{ $ok('seguimiento') }}</span>@endif
                    <button wire:click="guardarSeguimiento" class="px-4 py-2 bg-indigo-600 text-white text-sm rounded-md hover:bg-indigo-700">
                        Guardar seguimiento
                    </button>
                </div>
            </section>
        </div>

        {{-- ============ Columna lateral ============ --}}
        <aside class="space-y-6">

            {{-- Contacto --}}
            <section class="bg-white shadow-sm sm:rounded-lg p-6 text-sm text-gray-900">
                <h3 class="font-semibold mb-3">Contacto</h3>
                @if ($contacto)
                    <p class="text-gray-600 mb-3">La persona quiere respuesta individual.</p>
                    @if ($mostrarContacto)
                        <p class="font-mono bg-amber-50 border border-amber-200 rounded px-2 py-1 break-all">{{ $contacto->canal }}</p>
                    @endif
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button wire:click="revelarContacto" class="px-3 py-1.5 rounded-md border border-gray-300 hover:bg-gray-50">
                            {{ $mostrarContacto ? 'Ocultar' : 'Mostrar contacto' }}
                        </button>
                        @if ($contacto->respondido)
                            <span class="px-3 py-1.5 text-green-700">Respondido el {{ $contacto->fecha_respuesta?->format('d/m/Y') }}</span>
                        @else
                            <button wire:click="marcarRespondido" wire:confirm="¿Marcar como respondido?"
                                    class="px-3 py-1.5 rounded-md border border-gray-300 hover:bg-gray-50">
                                Marcar respondido
                            </button>
                        @endif
                    </div>
                    <p class="mt-2 text-xs text-gray-400">Cada consulta del contacto queda registrada.</p>
                @else
                    <p class="text-gray-400">Envío anónimo, sin contacto.</p>
                @endif
            </section>

            {{-- Historial --}}
            <section class="bg-white shadow-sm sm:rounded-lg p-6 text-sm text-gray-900">
                <h3 class="font-semibold mb-3">Historial</h3>
                @forelse ($this->historial as $h)
                    <div class="py-2 border-b border-gray-100 last:border-0" wire:key="h-{{ $h->id }}">
                        <div class="flex justify-between gap-2">
                            <span class="font-medium">{{ $h->etiqueta() }}</span>
                            <span class="text-xs text-gray-400 whitespace-nowrap">{{ $h->created_at?->format('d/m H:i') }}</span>
                        </div>
                        <div class="text-xs text-gray-500">{{ $h->usuario?->name ?? 'Sistema' }}</div>
                        @foreach ($h->cambios ?? [] as $campo => $valor)
                            <div class="text-xs text-gray-600">
                                {{ str_replace('_', ' ', $campo) }}:
                                @if (is_array($valor) && array_is_list($valor) && count($valor) === 2)
                                    @php
                                        $formatear = fn ($v) => is_array($v) ? (implode(', ', $v) ?: '—') : (is_bool($v) ? ($v ? 'sí' : 'no') : ($v ?? '—'));
                                    @endphp
                                    {{ $formatear($valor[0]) }} → {{ $formatear($valor[1]) }}
                                @else
                                    {{ is_array($valor) ? json_encode($valor, JSON_UNESCAPED_UNICODE) : $valor }}
                                @endif
                            </div>
                        @endforeach
                        @if ($h->motivo)<div class="text-xs text-gray-600 italic">«{{ $h->motivo }}»</div>@endif
                    </div>
                @empty
                    <p class="text-gray-400">Sin actividad todavía.</p>
                @endforelse
            </section>

            {{-- Eliminar --}}
            @unless ($s->trashed())
                <section x-data="{ abierto: false }" class="bg-white shadow-sm sm:rounded-lg p-6 text-sm text-gray-900">
                    <h3 class="font-semibold mb-1">Envío indebido</h3>
                    <p class="text-gray-500 mb-3">Spam, insultos, pruebas o contenido fuera del buzón. Va a la papelera y se puede restaurar.</p>
                    <button type="button" x-show="! abierto" @click="abierto = true"
                            class="px-3 py-1.5 rounded-md border border-red-300 text-red-700 hover:bg-red-50">
                        Enviar a la papelera…
                    </button>

                    <div x-show="abierto" style="display: none" class="space-y-3">
                        <select wire:model.live="motivoEliminacion" class="w-full rounded-md border-gray-300 text-sm">
                            <option value="">Motivo…</option>
                            @foreach (\App\Models\Sugerencia::MOTIVOS_ELIMINACION as $valor => $etiqueta)
                                <option value="{{ $valor }}">{{ $etiqueta }}</option>
                            @endforeach
                        </select>
                        @error('motivoEliminacion')<span class="text-red-600">{{ $message }}</span>@enderror

                        <textarea wire:model="detalleEliminacion" rows="2" maxlength="500"
                                  class="w-full rounded-md border-gray-300 text-sm"
                                  placeholder="Detalle {{ $motivoEliminacion === 'otro' ? '(obligatorio)' : '(opcional)' }}"></textarea>
                        @error('detalleEliminacion')<span class="text-red-600">{{ $message }}</span>@enderror

                        <div class="flex gap-2">
                            <button wire:click="eliminar" class="px-3 py-1.5 rounded-md bg-red-600 text-white hover:bg-red-700">Confirmar</button>
                            <button type="button" @click="abierto = false" class="px-3 py-1.5 rounded-md border border-gray-300 hover:bg-gray-50">Cancelar</button>
                        </div>
                    </div>
                </section>
            @endunless
        </aside>
    </div>
</div>
