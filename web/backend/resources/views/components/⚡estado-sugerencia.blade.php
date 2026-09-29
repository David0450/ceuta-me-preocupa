<?php

use App\Models\Sugerencia;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component {
    public Sugerencia $sugerencia;

    public string $estado = '';

    // Estados permitidos (los mismos del enum del MVP).
    public array $estados = [
        'recibido'    => 'Recibido',
        'en_revision' => 'En revisión',
        'clasificado' => 'Clasificado',
        'derivado'    => 'Derivado',
        'cerrado'     => 'Cerrado',
    ];

    public function mount(Sugerencia $sugerencia): void
    {
        $this->sugerencia = $sugerencia;
        $this->estado = $sugerencia->estado;
    }

    public function actualizar(): void
    {
        $this->validate([
            'estado' => ['required', Rule::in(array_keys($this->estados))],
        ]);

        $this->sugerencia->update(['estado' => $this->estado]);

        // Cuando exista la tabla historial_estados, aquí escribiríamos la traza:
        // estado anterior, nuevo, usuario y fecha.

        session()->flash('estado_ok', 'Estado actualizado.');
    }
}; ?>

<div class="flex flex-wrap items-center gap-3">
    <select wire:model="estado" class="border-gray-300 rounded-md text-sm">
        @foreach ($estados as $valor => $etiqueta)
            <option value="{{ $valor }}">{{ $etiqueta }}</option>
        @endforeach
    </select>

    <button wire:click="actualizar" wire:loading.attr="disabled" wire:target="actualizar"
            class="inline-flex items-center px-3 py-1.5 bg-indigo-600 text-white text-sm rounded-md hover:bg-indigo-700 disabled:opacity-50">
        <span wire:loading.remove wire:target="actualizar">Guardar</span>
        <span wire:loading wire:target="actualizar">Guardando…</span>
    </button>

    @if (session('estado_ok'))
        <span class="text-sm text-green-600">{{ session('estado_ok') }}</span>
    @endif

    @error('estado')
        <span class="text-sm text-red-600">{{ $message }}</span>
    @enderror
</div>
