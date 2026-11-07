// Site de operação: Livewire e Alpine num bundle só.
// O layout usa @livewireScriptConfig, então o Livewire não injeta outro Alpine na página.
import { Livewire, Alpine } from '../../../vendor/livewire/livewire/dist/livewire.esm';
import { vigiarConexao } from './conexao';
import copiar from './copiar';
import mesa from './mesa';
import relogioTorneio from './relogio-torneio';
import { aviso, campainha, tocarAgora } from './som';
import { manterAcesa } from './tela-acesa';
import tv from './tv';

// Componentes Alpine do site, registrados antes do start.
Alpine.data('mesa', mesa);
Alpine.data('relogioTorneio', relogioTorneio);
Alpine.data('tv', tv);

// $copiar(texto), $tocar('aviso' | 'campainha') num clique e $manterAcesa() (tela acesa).
Alpine.magic('copiar', () => copiar);
Alpine.magic('tocar', () => (nome) => tocarAgora(nome === 'aviso' ? aviso : campainha));
Alpine.magic('manterAcesa', () => manterAcesa);

window.Alpine = Alpine;

// Toque que não chegou ao servidor, poll sem rede e sessão vencida (conexao.js).
vigiarConexao(Livewire);

Livewire.start();
