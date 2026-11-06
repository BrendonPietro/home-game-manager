{{--
	v3: relógio do torneio para outras telas (TV, tablet, celular, notebook): /relogio/{codigo}, sem
	login e só para ver (RelogioPublicoController). Só o relógio: nível, tempo, blinds e ante, o que vem
	depois e o intervalo (em tempo e em níveis); nada de dinheiro nem de jogadores. O motor
	(relogio-torneio.js) busca o estado a cada 2 s e anda sozinho sem internet; som, tela cheia e tela
	acesa vêm do tv.js.
--}}
@php
	use App\Enums\StatusJogo;
	use App\Models\EstruturaNivel;
	use App\Support\Datas;

	$quando = $jogo->data ? Datas::porExtenso($jogo->data).($jogo->horario ? ' · '.Datas::hora($jogo->horario) : '') : null;
	$primeiro = $jogo->estruturaBlinds?->niveis->first(fn (EstruturaNivel $n) => ! $n->e_intervalo);
	$intervaloEm = $estado?->intervaloEm();
	$niveisAteIntervalo = $estado ? ($pacote['relogio']['niveis'][$pacote['relogio']['indice']]['niveisAteIntervalo'] ?? null) : null;
@endphp

<x-site.app :titulo="'Relógio · '.$jogo->nome">
	<div
		class="rh-tv rh-tv--relogio"
		x-data="tv"
		x-on:mousemove.window.throttle.500ms="mexeu()"
		x-on:pointerdown.window="mexeu(); destravar()"
		x-on:keydown.window="mexeu(); destravar()"
		x-on:relogio-trocou="campainha()"
	>
		<header class="rh-tv__topo">
			<x-site.props.brand />
			<div class="rh-tv__jogo">
				<b>{{ $jogo->nome }}</b>
				@if ($quando)
					<span>{{ $quando }}</span>
				@endif
			</div>
			<span class="rh-spacer"></span>

			<div class="rh-tv__botoes" x-bind:class="{ 'is-escondido': ! controles }">
				<span class="rh-tv__toque" x-show="som && travado" x-cloak>Toque na tela para ligar o som</span>
				<button type="button" class="rh-btn" x-on:click="alternarSom()" x-bind:aria-pressed="som ? 'true' : 'false'">
					<span x-show="som" x-cloak>@svg('rh-som')</span><span x-show="! som">@svg('rh-mudo')</span>
					<span class="rh-tv__botao-texto" x-text="som ? 'SOM LIGADO' : 'SOM DESLIGADO'">SOM DESLIGADO</span>
				</button>
				<button type="button" class="rh-btn" x-on:click="telaCheia()" x-show="podeTelaCheia">@svg('rh-expand') <span class="rh-tv__botao-texto" x-text="cheia ? 'SAIR DA TELA CHEIA' : 'TELA CHEIA'">TELA CHEIA</span></button>
			</div>
		</header>

		{{-- O motor busca o estado sozinho (data-url) e recarrega a página quando a chave muda. --}}
		<div
			class="rh-tv__corpo"
			x-data="relogioTorneio"
			data-url="{{ route('relogio.estado', $jogo->relogio_codigo) }}"
			data-chave="{{ $pacote['chave'] }}"
			@if ($pacote['relogio']) data-relogio="{{ json_encode($pacote['relogio']) }}" @endif
		>
			@if ($estado)
				<main class="rh-tv__palco sem-lado">
					<x-site.poker.painel-relogio :estado="$estado" />
				</main>

				<footer class="rh-tv__numeros">
					<div x-show="intervaloEm !== null" @if ($intervaloEm === null) style="display:none" @endif>
						<span class="rh-label">Intervalo em <span x-text="niveisAteIntervalo">{{ $niveisAteIntervalo ? $niveisAteIntervalo.' '.($niveisAteIntervalo === 1 ? 'nível' : 'níveis') : '' }}</span></span>
						<span class="v"><x-site.poker.relogio :estado="$estado" :segundos="($intervaloEm ?? 0) - $estado->fracao" /></span>
					</div>
					<div x-show="intervaloEm === null" @if ($intervaloEm !== null) style="display:none" @endif>
						<span class="rh-label">Intervalo</span>
						<span class="v rh-tv__sem-intervalo" x-text="noIntervalo ? 'Agora' : 'Sem intervalo pela frente'">{{ $estado->noIntervalo() ? 'Agora' : 'Sem intervalo pela frente' }}</span>
					</div>
				</footer>
			@elseif ($pacote['manual'])
				{{-- Torneio sem estrutura de blinds: nível e blinds digitados na tela ao vivo. --}}
				<main class="rh-tv__palco sem-lado">
					<section class="rh-tv__relogio sem-relogio">
						<div class="rh-tv__titulo">{{ $pacote['manual']['titulo'] }}</div>
						@if ($pacote['manual']['blinds'])
							<div class="rh-tv__blinds"><span class="v">{{ $pacote['manual']['blinds'] }}</span></div>
						@endif
					</section>
				</main>
			@else
				{{-- Antes de começar, depois de acabar ou cancelado: o nome grande e o que der para mostrar. --}}
				<main class="rh-tv__fora">
					<div class="rh-tv__titulo">{{ match ($jogo->status) {
						StatusJogo::Agendado => $quando ?? 'Em breve',
						StatusJogo::Finalizado => 'Fim de jogo',
						default => 'Jogo cancelado',
					} }}</div>
					<div class="rh-tv__nome">{{ $jogo->nome }}</div>
					@if ($jogo->status === StatusJogo::Agendado)
						<div class="rh-tv__proximo">O relógio começa quando o torneio for iniciado{{ $primeiro ? ' · nível 1: '.$primeiro->blindsComAnte().' · '.$primeiro->duracao_minutos.' min' : '' }}</div>
					@endif
				</main>
			@endif
		</div>
	</div>
</x-site.app>
