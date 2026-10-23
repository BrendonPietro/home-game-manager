// Site de operação: Livewire e Alpine num bundle só.
// O layout usa @livewireScriptConfig, então o Livewire não injeta outro Alpine na página.
import { Livewire, Alpine } from '../../../vendor/livewire/livewire/dist/livewire.esm';
import mesa from './mesa';

// Componentes Alpine do site, registrados antes do start.
Alpine.data('mesa', mesa);

window.Alpine = Alpine;

Livewire.start();
