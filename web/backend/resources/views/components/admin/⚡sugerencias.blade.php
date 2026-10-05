<?php

use App\Models\Categoria;
use App\Models\Organismo;
use App\Models\Sugerencia;
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

    public function updated($propiedad): void
    {
        if ($propiedad === 'categoria') {
            $this->subcategoria = '';
        }
        $this->resetPage();
    }

    public int $reinicio = 0;

    public function limpiar(): void
    {
        $this->reset(['buscar', 'categoria', 'subcategoria', 'organismo', 'estado', 'desde', 'hasta']);
        $this->reinicio++;
        $this->resetPage();
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
    public function sugerencias()
    {
        return Sugerencia::query()
            ->with(['categoria', 'subcategorias'])
            ->withCount('contacto')
            ->when($this->buscar, fn ($q) => $q->where('texto', 'like', '%' . $this->buscar . '%'))
            ->when($this->categoria, fn ($q) => $q->where('categoria_id', $this->categoria))
            ->when($this->subcategoria, fn ($q) => $q->whereHas('subcategorias',
                fn ($s) => $s->where('subcategorias.id', $this->subcategoria)))
            ->when($this->organismo, fn ($q) => $q->where('organismo_id', $this->organismo))
            ->when($this->estado, fn ($q) => $q->where('estado', $this->estado))
            ->when($this->desde, fn ($q) => $q->whereDate('fecha_recepcion', '>=', $this->desde))
            ->when($this->hasta, fn ($q) => $q->whereDate('fecha_recepcion', '<=', $this->hasta))
            ->latest('fecha_recepcion')
            ->paginate(20);
    }
};
?>

<div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6 text-gray-900">

            {{-- Filtros --}}
            <div class="mb-6 space-y-4 text-sm" wire:key="filtros-{{ $reinicio }}">

                {{-- Fila 1: búsqueda + limpiar --}}
                <div class="flex gap-3">
                    <div class="relative flex-1">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>
                        </svg>
                        <input type="search" wire:model.live.debounce.400ms="buscar"
                               placeholder="Buscar en el texto de la sugerencia…"
                               class="w-full pl-9 rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <button wire:click="limpiar" type="button"
                            class="px-3 rounded-md border border-gray-300 text-gray-600 hover:bg-gray-50 whitespace-nowrap">
                        Limpiar filtros
                    </button>
                </div>

                {{-- Fila 2: desplegables y rango de fechas --}}
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
                            @foreach ($this->organismos as $o)
                                <option value="{{ $o->id }}">{{ $o->nombre }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="block">
                        <span class="block text-xs font-medium text-gray-500 mb-1">Estado</span>
                        <select wire:model.live="estado" class="w-full rounded-md border-gray-300 text-sm">
                            <option value="">Todos</option>
                            <option value="pendiente">Pendiente</option>
                            <option value="revisada">Revisada</option>
                            <option value="descartada">Descartada</option>
                        </select>
                    </label>

                    {{-- Rango de fechas agrupado --}}
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

            <p class="text-sm text-gray-500 mb-2">{{ $this->sugerencias->total() }} resultados</p>

            {{-- Tabla --}}
            @if ($this->sugerencias->total() === 0)
                <p class="text-gray-500">No hay sugerencias que coincidan con los filtros.</p>
            @else
                <div class="overflow-x-auto" wire:loading.class="opacity-50">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left border-b border-gray-200 text-gray-500">
                                <th class="py-2 pr-4">#</th>
                                <th class="py-2 pr-4">Fecha</th>
                                <th class="py-2 pr-4">Categoría</th>
                                <th class="py-2 pr-4">Subcategorías</th>
                                <th class="py-2 pr-4">Estado</th>
                                <th class="py-2 pr-4">Contacto</th>
                                <th class="py-2 pr-4"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->sugerencias as $sugerencia)
                                <tr class="border-b border-gray-100" wire:key="sugerencia-{{ $sugerencia->id }}">
                                    <td class="py-3 pr-4 text-gray-500">{{ $sugerencia->id }}</td>
                                    <td class="py-3 pr-4 whitespace-nowrap">{{ $sugerencia->fecha_recepcion?->format('d/m/Y H:i') }}</td>
                                    <td class="py-3 pr-4">{{ $sugerencia->categoria->nombre }}</td>
                                    <td class="py-3 pr-4 text-gray-600">{{ $sugerencia->subcategorias->pluck('nombre')->join(', ') }}</td>
                                    <td class="py-3 pr-4">
                                        <span class="inline-block px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-700">{{ ucfirst($sugerencia->estado) }}</span>
                                    </td>
                                    <td class="py-3 pr-4">{{ $sugerencia->contacto_count ? 'Sí' : '—' }}</td>
                                    <td class="py-3 pr-4">
                                        <a href="{{ route('admin.sugerencias.show', $sugerencia) }}" class="text-indigo-600 hover:text-indigo-800 font-medium">Ver</a>
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
