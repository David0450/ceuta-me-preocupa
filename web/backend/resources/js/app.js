import './bootstrap';

// Livewire ya incluye Alpine. Si además se arranca el Alpine de Breeze, hay dos
// instancias en la página y wire:model deja de reflejar los cambios del servidor.
import { Livewire } from '../../vendor/livewire/livewire/dist/livewire.esm';

Livewire.start();
