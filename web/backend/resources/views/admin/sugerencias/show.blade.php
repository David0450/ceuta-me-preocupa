<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Revisión de la sugerencia #{{ $sugerencia->id }}
            </h2>
            <a href="{{ route('admin.sugerencias.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800">&larr; Volver al listado</a>
        </div>
    </x-slot>

    <div class="py-12">
        <livewire:admin.revision-sugerencia :sugerencia="$sugerencia" />
    </div>
</x-app-layout>
