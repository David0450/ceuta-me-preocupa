<?php

use App\Models\Categoria;
use App\Models\Organismo;
use App\Models\Sugerencia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    #[Url(except: '')] public string $buscar = '';
    #[Url(except: '')] public string $categoria = '';
    #[Url(except: '')] public string $subcategoria = '';
    #[Url(except: '')] public string $organismo = '';
    #[Url(except: '')] public string $estado = '';
    #[Url(except: '')] public string $desde = '';
    #[Url(except: '')] public string $hasta = '';
    #[Url(except: '')] public string $revision = '';
    #[Url(except: false)] public bool $papelera = false;

    public int $reinicio = 0;

    /**
     * Filtros rápidos de revisión.
     *
     * @var array<string, string>
     */
    public const REVISIONES = [
        'pendiente' => 'Pendientes de revisión',
        'privacidad' => 'Sin revisión de privacidad',
        'clasificacion' => 'Sin clasificación confirmada',
        'competencia' => 'Competencia por confirmar',
        'urgente' => 'Urgentes o prioritarias',
        'sin_ia' => 'Sin procesar por la IA',
    ];

    public function boot(): void
    {
        Gate::authorize('revisar-sugerencias');
    }

    public function updated($propiedad): void
    {
        if ($propiedad === 'categoria') {
            $this->subcategoria = '';
        }
        $this->resetPage();
    }

    public function limpiar(): void
    {
        $this->reset(['buscar', 'categoria', 'subcategoria', 'organismo', 'estado', 'desde', 'hasta', 'revision']);
        $this->reinicio++;
        $this->resetPage();
    }

    public function filtrarRevision(string $revision): void
    {
        $this->papelera = false;
        $this->revision = $this->revision === $revision ? '' : $revision;
        $this->resetPage();
    }

    public function verPapelera(bool $ver): void
    {
        $this->papelera = $ver;
        $this->revision = '';
        $this->resetPage();
    }

    public function restaurar(int $id): void
    {
        $sugerencia = Sugerencia::onlyTrashed()->findOrFail($id);

        $sugerencia->restore();
        $sugerencia->update(['motivo_eliminacion' => null, 'eliminada_por' => null]);
        $sugerencia->registrar('restaurada');

        session()->flash('estado_panel', "Sugerencia #{$id} restaurada.");
    }

    /**
     * Borrado físico: elimina también el texto original, el contacto, las
     * subcategorías y las propuestas de la IA (cascada). El historial se conserva.
     */
    public function eliminarDefinitivamente(int $id): void
    {
        $sugerencia = Sugerencia::onlyTrashed()->findOrFail($id);

        DB::transaction(function () use ($sugerencia) {
            $sugerencia->registrar('eliminada_definitivamente', ['motivo' => $sugerencia->motivo_eliminacion]);
            $sugerencia->forceDelete();
        });

        session()->flash('estado_panel', "Sugerencia #{$id} eliminada definitivamente.");
    }

    #[Computed]
    public function categorias() { return Categoria::orderBy('nombre')->get(); }

    #[Computed]
    public function subcategorias()
    {
        return $this->categoria
            ? Categoria::find($this->categoria)?->subcategorias()->orderBy('nombre')->get() ?? collect()
            : collect();
    }

    #[Computed]
    public function organismos() { return Organismo::orderBy('nombre')->get(); }

    #[Computed]
    public function contadores(): array
    {
        return [
            'pendiente' => Sugerencia::where(fn ($q) => $q->whereNull('privacidad_revisada_at')->orWhereNull('clasificacion_revisada_at'))->count(),
            'privacidad' => Sugerencia::whereNull('privacidad_revisada_at')->count(),
            'clasificacion' => Sugerencia::whereNull('clasificacion_revisada_at')->count(),
            'competencia' => Sugerencia::where('competencia_por_confirmar', true)->count(),
            'urgente' => Sugerencia::whereRaw(Sugerencia::sqlRangoUrgencia().' <= 1')->whereNot('estado', 'cerrado')->count(),
            'sin_ia' => Sugerencia::whereDoesntHave('propuestasIa')->count(),
            'papelera' => Sugerencia::onlyTrashed()->count(),
        ];
    }

    #[Computed]
    public function sugerencias()
    {
        $buscar = trim($this->buscar);

        return Sugerencia::query()
            ->when($this->papelera, fn ($q) => $q->onlyTrashed()->with('eliminadaPor'))
            ->with(['categoria', 'subcategorias', 'organismo', 'propuestaIa'])
            ->withCount('contacto')
            // Solo se busca en la versión desidentificada (nunca en el texto original).
            ->when($buscar !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('texto_desidentificado', 'like', '%'.$buscar.'%')
                ->when(ctype_digit(ltrim($buscar, '#')), fn ($w) => $w->orWhere('id', (int) ltrim($buscar, '#')))))
            ->when($this->categoria, fn ($q) => $q->where('categoria_id', $this->categoria))
            ->when($this->subcategoria, fn ($q) => $q->whereHas('subcategorias',
                fn ($s) => $s->where('subcategorias.id', $this->subcategoria)))
            ->when($this->organismo === 'sin', fn ($q) => $q->whereNull('organismo_id'))
            ->when($this->organismo && $this->organismo !== 'sin', fn ($q) => $q->where('organismo_id', $this->organismo))
            ->when($this->estado, fn ($q) => $q->where('estado', $this->estado))
            ->when($this->desde, fn ($q) => $q->whereDate('fecha_recepcion', '>=', $this->desde))
            ->when($this->hasta, fn ($q) => $q->whereDate('fecha_recepcion', '<=', $this->hasta))
            ->when($this->revision === 'pendiente', fn ($q) => $q->where(fn ($w) => $w
                ->whereNull('privacidad_revisada_at')->orWhereNull('clasificacion_revisada_at')))
            ->when($this->revision === 'privacidad', fn ($q) => $q->whereNull('privacidad_revisada_at'))
            ->when($this->revision === 'clasificacion', fn ($q) => $q->whereNull('clasificacion_revisada_at'))
            ->when($this->revision === 'competencia', fn ($q) => $q->where('competencia_por_confirmar', true))
            // Incluye las que la IA marca como urgentes aunque nadie las haya revisado aún.
            ->when($this->revision === 'urgente', fn ($q) => $q->whereRaw(Sugerencia::sqlRangoUrgencia().' <= 1')->whereNot('estado', 'cerrado'))
            ->when($this->revision === 'sin_ia', fn ($q) => $q->whereDoesntHave('propuestasIa'))
            // Lo urgente primero (también lo que la IA marca como urgente y está sin
            // revisar); dentro de cada nivel, lo más reciente.
            ->orderByRaw(Sugerencia::sqlRangoUrgencia())
            ->latest('fecha_recepcion')
            ->paginate(20);
    }
};
?>

@php
    $estadoClases = [
        'recibido' => 'bg-gray-100 text-gray-700',
        'en_revision' => 'bg-amber-100 text-amber-800',
        'clasificado' => 'bg-sky-100 text-sky-800',
        'derivado' => 'bg-indigo-100 text-indigo-800',
        'cerrado' => 'bg-green-100 text-green-800',
    ];
@endphp

<div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

    @if (session('estado_panel'))
        <div class="bg-green-50 border border-green-200 text-green-800 text-sm sm:rounded-lg px-4 py-3">{{ session('estado_panel') }}</div>
    @endif

    {{-- Filtros rápidos de revisión --}}
    <div class="flex flex-wrap gap-2 text-sm">
        @foreach ($this::REVISIONES as $clave => $etiqueta)
            <button type="button" wire:click="filtrarRevision('{{ $clave }}')"
                    class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full border
                           {{ $revision === $clave && ! $papelera ? 'bg-indigo-600 border-indigo-600 text-white' : 'bg-white border-gray-200 text-gray-700 hover:bg-gray-50' }}">
                {{ $etiqueta }}
                <span class="tabular-nums text-xs px-1.5 rounded-full {{ $revision === $clave && ! $papelera ? 'bg-indigo-500' : 'bg-gray-100' }}">{{ $this->contadores[$clave] }}</span>
            </button>
        @endforeach

        <button type="button" wire:click="verPapelera({{ $papelera ? 'false' : 'true' }})"
                class="ml-auto inline-flex items-center gap-2 px-3 py-1.5 rounded-full border
                       {{ $papelera ? 'bg-red-600 border-red-600 text-white' : 'bg-white border-gray-200 text-gray-700 hover:bg-gray-50' }}">
            {{ $papelera ? '← Salir de la papelera' : 'Papelera' }}
            <span class="tabular-nums text-xs px-1.5 rounded-full {{ $papelera ? 'bg-red-500' : 'bg-gray-100' }}">{{ $this->contadores['papelera'] }}</span>
        </button>
    </div>

    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6 text-gray-900">

            {{-- Filtros --}}
            <div class="mb-6 space-y-4 text-sm" wire:key="filtros-{{ $reinicio }}">

                <div class="flex gap-3">
                    <div class="relative flex-1">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>
                        </svg>
                        <input type="search" wire:model.live.debounce.400ms="buscar"
                               placeholder="Buscar por nº o en el texto desidentificado…"
                               class="w-full pl-9 rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <button wire:click="limpiar" type="button"
                            class="px-3 rounded-md border border-gray-300 text-gray-600 hover:bg-gray-50 whitespace-nowrap">
                        Limpiar filtros
                    </button>
                </div>

                <div class="grid grid-cols-2 lg:grid-cols-6 gap-3 items-end">
                    <label class="block">
                        <span class="block text-xs font-medium text-gray-500 mb-1">Categoría</span>
                        <select wire:model.live="categoria" class="w-full rounded-md border-gray-300 text-sm">
                            <option value="">Todas</option>
                            @foreach ($this->categorias as $c)
                                <option value="{{ $c->id }}">{{ $c->nombre }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="block">
                        <span class="block text-xs font-medium text-gray-500 mb-1">Subcategoría</span>
                        <select wire:model.live="subcategoria" @disabled(!$categoria)
                                class="w-full rounded-md border-gray-300 text-sm disabled:bg-gray-100 disabled:text-gray-400">
                            <option value="">{{ $categoria ? 'Todas' : 'Elige categoría' }}</option>
                            @foreach ($this->subcategorias as $s)
                                <option value="{{ $s->id }}">{{ $s->nombre }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="block">
                        <span class="block text-xs font-medium text-gray-500 mb-1">Organismo</span>
                        <select wire:model.live="organismo" class="w-full rounded-md border-gray-300 text-sm">
                            <option value="">Todos</option>
                            <option value="sin">Sin asignar</option>
                            @foreach ($this->organismos as $o)
                                <option value="{{ $o->id }}">{{ $o->nombre }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="block">
                        <span class="block text-xs font-medium text-gray-500 mb-1">Estado</span>
                        <select wire:model.live="estado" class="w-full rounded-md border-gray-300 text-sm">
                            <option value="">Todos</option>
                            @foreach (\App\Models\Sugerencia::ESTADOS as $valor => $etiqueta)
                                <option value="{{ $valor }}">{{ $etiqueta }}</option>
                            @endforeach
                        </select>
                    </label>

                    <fieldset class="col-span-2">
                        <legend class="block text-xs font-medium text-gray-500 mb-1">Fecha de recepción</legend>
                        <div class="flex items-center gap-2">
                            <input type="date" wire:model.live="desde" max="{{ $hasta ?: '' }}"
                                   aria-label="Desde" title="Desde"
                                   class="flex-1 min-w-0 rounded-md border-gray-300 text-sm">
                            <span class="text-gray-400">→</span>
                            <input type="date" wire:model.live="hasta" min="{{ $desde ?: '' }}"
                                   aria-label="Hasta" title="Hasta"
                                   class="flex-1 min-w-0 rounded-md border-gray-300 text-sm">
                        </div>
                    </fieldset>
                </div>
            </div>

            <p class="text-sm text-gray-500 mb-2">
                {{ $this->sugerencias->total() }} resultados{{ $papelera ? ' en la papelera' : '' }}
            </p>

            @if ($this->sugerencias->total() === 0)
                <p class="text-gray-500">{{ $papelera ? 'La papelera está vacía.' : 'No hay sugerencias que coincidan con los filtros.' }}</p>
            @else
                <div class="overflow-x-auto" wire:loading.class="opacity-50">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left border-b border-gray-200 text-gray-500">
                                <th class="py-2 pr-4">#</th>
                                <th class="py-2 pr-4">Fecha</th>
                                <th class="py-2 pr-4">Categoría</th>
                                <th class="py-2 pr-4">Organismo</th>
                                <th class="py-2 pr-4">Estado</th>
                                @if ($papelera)
                                    <th class="py-2 pr-4">Motivo</th>
                                @else
                                    <th class="py-2 pr-4">Revisión</th>
                                    <th class="py-2 pr-4">Contacto</th>
                                @endif
                                <th class="py-2 pr-4"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->sugerencias as $sugerencia)
                                <tr class="border-b border-gray-100 align-top" wire:key="sugerencia-{{ $sugerencia->id }}">
                                    <td class="py-3 pr-4 text-gray-500">
                                        {{ $sugerencia->id }}
                                        @php $urgencia = $sugerencia->urgenciaEfectiva(); @endphp
                                        @if ($urgencia !== 'ordinaria')
                                            <span class="block mt-1 text-[10px] font-semibold uppercase {{ $urgencia === 'urgente' ? 'text-red-600' : 'text-orange-600' }}"
                                                  @if ($sugerencia->urgenciaPorIa()) title="Propuesta por la IA, pendiente de confirmar" @endif>
                                                {{ \App\Models\Sugerencia::URGENCIAS[$urgencia] ?? '' }}@if ($sugerencia->urgenciaPorIa()) · IA @endif
                                            </span>
                                        @endif
                                    </td>
                                    <td class="py-3 pr-4 whitespace-nowrap">{{ $sugerencia->fecha_recepcion?->format('d/m/Y H:i') }}</td>
                                    <td class="py-3 pr-4">
                                        {{ $sugerencia->categoria->nombre }}
                                        <span class="block text-xs text-gray-500">{{ $sugerencia->subcategorias->pluck('nombre')->join(', ') }}</span>
                                    </td>
                                    <td class="py-3 pr-4 text-gray-600">
                                        @if ($sugerencia->competencia_por_confirmar)
                                            <span class="text-orange-700">Por confirmar</span>
                                        @else
                                            {{ $sugerencia->organismo?->nombre ?? '—' }}
                                        @endif
                                    </td>
                                    <td class="py-3 pr-4">
                                        <span class="inline-block px-2 py-0.5 rounded-full text-xs {{ $estadoClases[$sugerencia->estado] ?? 'bg-gray-100 text-gray-700' }}">{{ $sugerencia->etiquetaEstado() }}</span>
                                    </td>
                                    @if ($papelera)
                                        <td class="py-3 pr-4 text-gray-600">
                                            {{ \App\Models\Sugerencia::MOTIVOS_ELIMINACION[$sugerencia->motivo_eliminacion] ?? '—' }}
                                            <span class="block text-xs text-gray-400">{{ $sugerencia->eliminadaPor?->name }} · {{ $sugerencia->deleted_at?->format('d/m/Y') }}</span>
                                        </td>
                                    @else
                                        <td class="py-3 pr-4 whitespace-nowrap text-xs">
                                            <span title="Privacidad" class="{{ $sugerencia->privacidadRevisada() ? 'text-green-700' : 'text-gray-400' }}">{{ $sugerencia->privacidadRevisada() ? '✓' : '○' }} Privacidad</span><br>
                                            <span title="Clasificación" class="{{ $sugerencia->clasificacionRevisada() ? 'text-green-700' : 'text-gray-400' }}">{{ $sugerencia->clasificacionRevisada() ? '✓' : '○' }} Clasificación</span>
                                        </td>
                                        <td class="py-3 pr-4">{{ $sugerencia->contacto_count ? 'Sí' : '—' }}</td>
                                    @endif
                                    <td class="py-3 pr-4 whitespace-nowrap text-right">
                                        <a href="{{ route('admin.sugerencias.show', $sugerencia) }}" class="text-indigo-600 hover:text-indigo-800 font-medium">
                                            {{ $papelera ? 'Ver' : 'Revisar' }}
                                        </a>
                                        @if ($papelera)
                                            <button wire:click="restaurar({{ $sugerencia->id }})" class="ml-3 text-gray-700 hover:text-gray-900">Restaurar</button>
                                            <button wire:click="eliminarDefinitivamente({{ $sugerencia->id }})"
                                                    wire:confirm="Se borrarán para siempre el texto original, el contacto y la clasificación de la sugerencia #{{ $sugerencia->id }}. Solo quedará la traza en el historial. ¿Continuar?"
                                                    class="ml-3 text-red-600 hover:text-red-800">Eliminar</button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $this->sugerencias->links() }}
                </div>
            @endif

        </div>
    </div>
</div>
