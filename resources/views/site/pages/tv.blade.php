{{-- v2: tela cheia para a TV. Sem topbar nem fluxo da noite: só o Livewire Jogo\Tv. --}}
<x-site.app :titulo="'TV · '.$jogo->nome">
	<livewire:jogo.tv :jogo="$jogo" />
</x-site.app>
