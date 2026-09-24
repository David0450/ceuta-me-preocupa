<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Sugerencia #{{ $sugerencia->id }}
            </h2>
            <a href="{{ route('admin.sugerencias.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800">&larr; Volver al listado</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Ficha de trabajo (no sensible) --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-6 text-gray-900">
                <h3 class="font-semibold text-lg mb-4">Ficha</h3>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                    <div><dt class="text-gray-500">Fecha de recepción</dt><dd>{{ $sugerencia->fecha_recepcion?->format('d/m/Y H:i') }}</dd></div>
                    <div><dt class="text-gray-500">Estado</dt><dd>{{ ucfirst($sugerencia->estado) }}</dd></div>
                    <div><dt class="text-gray-500">Categoría</dt><dd>{{ $sugerencia->categoria->nombre }}</dd></div>
                    <div><dt class="text-gray-500">Subcategorías</dt><dd>{{ $sugerencia->subcategorias->pluck('nombre')->join(', ') ?: '—' }}</dd></div>
                    <div><dt class="text-gray-500">Organismo</dt><dd>{{ $sugerencia->organismo?->nombre ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">Versión aviso privacidad</dt><dd>{{ $sugerencia->version_aviso_privacidad }}</dd></div>
                </dl>
            </div>

            {{-- Texto desidentificado (cuando exista) --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-6 text-gray-900">
                <h3 class="font-semibold text-lg mb-3">Texto desidentificado</h3>
                @if ($sugerencia->texto_desidentificado)
                    <p class="whitespace-pre-wrap text-gray-800">{{ $sugerencia->texto_desidentificado }}</p>
                @else
                    <p class="text-gray-400 italic">Pendiente de anonimizar.</p>
                @endif
            </div>

            {{-- Datos sensibles --}}
            <div class="bg-amber-50 border border-amber-200 sm:rounded-lg p-6">
                <h3 class="font-semibold text-lg text-amber-900 mb-1">Datos originales (sensibles)</h3>
                <p class="text-xs text-amber-700 mb-4">Contienen datos personales sin desidentificar. Acceso restringido.</p>

                @if ($sugerencia->datoOriginal)
                    <div class="space-y-3 text-sm text-gray-900">
                        <div>
                            <span class="text-gray-500">Texto original:</span>
                            <p class="whitespace-pre-wrap mt-1">{{ $sugerencia->datoOriginal->texto_original }}</p>
                        </div>
                        @if ($sugerencia->datoOriginal->resultado_esperado_original)
                            <div>
                                <span class="text-gray-500">Resultado esperado:</span>
                                <p class="whitespace-pre-wrap mt-1">{{ $sugerencia->datoOriginal->resultado_esperado_original }}</p>
                            </div>
                        @endif
                        @if ($sugerencia->datoOriginal->zona_origen)
                            <div><span class="text-gray-500">Zona:</span> {{ $sugerencia->datoOriginal->zona_origen }}</div>
                        @endif
                        @if ($sugerencia->datoOriginal->organismo_sugerido_ciudadano)
                            <div><span class="text-gray-500">Organismo sugerido por la persona:</span> {{ $sugerencia->datoOriginal->organismo_sugerido_ciudadano }}</div>
                        @endif
                    </div>
                @else
                    <p class="text-gray-400 italic">Sin datos originales.</p>
                @endif

                @if ($sugerencia->contacto)
                    <div class="mt-4 pt-4 border-t border-amber-200 text-sm text-gray-900">
                        <span class="text-gray-500">Contacto (la persona quiere respuesta):</span>
                        <span class="font-medium">{{ $sugerencia->contacto->canal }}</span>
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
