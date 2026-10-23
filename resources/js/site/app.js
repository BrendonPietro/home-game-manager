// Site de operação: Livewire e Alpine num bundle só.
// O layout usa @livewireScriptConfig, então o Livewire não injeta outro Alpine na página.
import { Livewire, Alpine } from '../../../vendor/livewire/livewire/dist/livewire.esm';

// Componentes Alpine do site entram aqui antes do start (ex.: Alpine.data('mesa', mesa) na fase 3).
window.Alpine = Alpine;

Livewire.start();
