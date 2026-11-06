// Site de operação: Livewire e Alpine num bundle só.
// O layout usa @livewireScriptConfig, então o Livewire não injeta outro Alpine na página.
import { Livewire, Alpine } from '../../../vendor/livewire/livewire/dist/livewire.esm';
import copiar from './copiar';
import mesa from './mesa';
import relogioTorneio from './relogio-torneio';
import tv from './tv';

// Componentes Alpine do site, registrados antes do start.
Alpine.data('mesa', mesa);
Alpine.data('relogioTorneio', relogioTorneio);
Alpine.data('tv', tv);
Alpine.magic('copiar', () => copiar);

window.Alpine = Alpine;

Livewire.start();
