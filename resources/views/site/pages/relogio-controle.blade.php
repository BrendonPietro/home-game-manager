{{-- v3: tela do relógio de quem controla o torneio. Sem topbar nem fluxo da noite: a tela toda é o relógio. --}}
<x-site.app :titulo="'Relógio · '.$jogo->nome">
	<livewire:jogo.controle-do-relogio :jogo="$jogo" />
</x-site.app>
