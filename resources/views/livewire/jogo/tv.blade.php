{{-- v2: tela cheia para a TV. Atualiza a cada 5 s (também em segundo plano); relógio, som e tela cheia no navegador. --}}
@php
	use App\Enums\StatusJogo;
	use App\Models\EstruturaNivel;
	use App\Support\Datas;
	use App\Support\Dinheiro;

	$andamento = $jogo->status === StatusJogo::EmAndamento;
	$torneio = $jogo->eTorneio();
	// Com estrutura de blinds, o painel do relógio (x-site.poker.painel-relogio) monta título, blinds e o que vem depois.
	$fase = $torneio ? $jogo->fase?->label() : 'Cash game';
@endphp

<div
	class="rh-tv"
	wire:poll.5s.keep-alive
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
			<span>{{ $andamento ? $fase : Datas::porExtenso($jogo->data).($jogo->horario ? ' · '.Datas::hora($jogo->horario) : '') }}</span>
		</div>
		<span class="rh-spacer"></span>

		{{-- Do Alpine: o poll não mexe nos botões. --}}
		<div class="rh-tv__botoes" wire:ignore x-bind:class="{ 'is-escondido': ! controles }">
			<span class="rh-tv__toque" x-show="som && travado" x-cloak>Toque na tela para ligar o som</span>
			<button type="button" class="rh-btn" x-on:click="alternarSom()" x-bind:aria-pressed="som ? 'true' : 'false'">
				<span x-show="som" x-cloak>@svg('rh-som')</span><span x-show="! som">@svg('rh-mudo')</span>
				<span x-text="som ? 'SOM LIGADO' : 'SOM DESLIGADO'">SOM DESLIGADO</span>
			</button>
			<button type="button" class="rh-btn" x-on:click="telaCheia()" x-show="podeTelaCheia">@svg('rh-expand') <span x-text="cheia ? 'SAIR DA TELA CHEIA' : 'TELA CHEIA'">TELA CHEIA</span></button>
			<a class="rh-btn rh-btn--ghost" href="{{ route('jogos.ao-vivo', $jogo) }}">VOLTAR</a>
		</div>
	</header>

	@if ($andamento)
		{{-- O motor do relógio (relogio-torneio.js) cobre o palco e os números: anda sozinho entre um poll e outro. --}}
		<div class="rh-tv__corpo" @if ($relogio) x-data="relogioTorneio" data-relogio="{{ json_encode($relogio->paraNavegador()) }}" @endif>
		<main @class(['rh-tv__palco', 'sem-lado' => ! $torneio])>
			@if ($relogio)
				<x-site.poker.painel-relogio :estado="$relogio" />
			@else
				{{-- Sem estrutura de blinds (v1): nível e blinds digitados na tela ao vivo. --}}
				<section class="rh-tv__relogio sem-relogio">
					<div class="rh-tv__titulo">{{ $jogo->nivel_atual ? 'Nível '.$jogo->nivel_atual : 'Blinds' }}</div>
					@if ($jogo->blinds_atual)
						<div class="rh-tv__blinds"><span class="v">{{ $jogo->blinds_atual }}</span></div>
					@endif
				</section>
			@endif

			@if ($torneio)
				<aside class="rh-tv__lado">
					<div class="rh-tv__prize">
						<span class="rh-label">Prize pool</span>
						<span class="v">{{ Dinheiro::brlCentavos($resumo->prizePool) }}</span>
					</div>
					@if ($premiacao)
						<div class="rh-tv__premios">
							<div class="rh-label">{{ $premiosGravados ? 'Premiação' : 'Premiação até agora' }}</div>
							@foreach ($premiacao as $premio)
								<div class="rh-tv__premio" wire:key="premio-{{ $premio['posicao'] }}">
									<span class="rh-tv__pos">{{ $premio['posicao'] }}º</span>
									<span class="rh-tv__quem">{{ $premio['quem'] }}</span>
									<span class="v">{{ Dinheiro::brlCentavos($premio['valor']) }}</span>
								</div>
							@endforeach
						</div>
					@endif
				</aside>
			@endif
		</main>

		<footer class="rh-tv__numeros">
			<div><span class="rh-label">Jogando</span><span class="v">{{ $jogando }}</span></div>
			<div><span class="rh-label">Entradas</span><span class="v">{{ $resumo->entradas }}</span></div>
			@if ($stackMedio)
				<div><span class="rh-label">Stack médio</span><span class="v">{{ EstruturaNivel::fichas($stackMedio) }}</span></div>
			@endif
			@if ($relogio)
				@php $intervaloEm = $relogio->intervaloEm(); @endphp
				<div wire:ignore x-show="intervaloEm !== null" @if ($intervaloEm === null) style="display:none" @endif>
					<span class="rh-label">Intervalo em <span x-text="niveisAteIntervalo"></span></span>
					<span class="v"><x-site.poker.relogio :estado="$relogio" :segundos="($intervaloEm ?? 0) - $relogio->fracao" /></span>
				</div>
			@endif
		</footer>
		</div>
	@else
		{{-- Antes de começar e depois de acabar: o nome grande e o que der para mostrar. --}}
		<main class="rh-tv__fora">
			<div class="rh-tv__titulo">{{ match ($jogo->status) { StatusJogo::Agendado => Datas::porExtenso($jogo->data).($jogo->horario ? ' · '.Datas::hora($jogo->horario) : ''), StatusJogo::Finalizado => 'Fim de jogo', default => 'Jogo cancelado' } }}</div>
			<div class="rh-tv__nome">{{ $jogo->nome }}</div>
			@if ($jogo->status === StatusJogo::Agendado)
				<div class="rh-tv__proximo">{{ $confirmados }} {{ $confirmados === 1 ? 'confirmado' : 'confirmados' }} · o relógio começa quando o jogo for iniciado</div>
			@elseif ($premiacao)
				<div class="rh-tv__premios rh-tv__premios--final">
					@foreach ($premiacao as $premio)
						<div class="rh-tv__premio" wire:key="final-{{ $premio['posicao'] }}">
							<span class="rh-tv__pos">{{ $premio['posicao'] }}º</span>
							<span class="rh-tv__quem">{{ $premio['quem'] }}</span>
							<span class="v">{{ Dinheiro::brlCentavos($premio['valor']) }}</span>
						</div>
					@endforeach
				</div>
			@endif
		</main>
	@endif
</div>
