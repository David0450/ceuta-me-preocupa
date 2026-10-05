<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Sugerencias recibidas
        </h2>
    </x-slot>

    <div class="py-12">
        {{--<div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">

                    @if ($sugerencias->total() === 0)
                        <p class="text-gray-500">Todavía no se ha recibido ninguna sugerencia.</p>
                    @else
                        <div class="overflow-x-auto">
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
                                    @foreach ($sugerencias as $sugerencia)
                                        <tr class="border-b border-gray-100">
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
                            {{ $sugerencias->links() }}
                        </div>
                    @endif

                </div>
            </div>
        </div>--}}
            <livewire:admin.sugerencias />
    </div>
</x-app-layout>
