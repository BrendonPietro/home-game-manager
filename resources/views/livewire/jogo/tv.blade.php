{{-- v2: tela cheia para a TV. Atualiza a cada 5 s (também em segundo plano); relógio, som e tela cheia no navegador. --}}
@php
	use App\Enums\StatusJogo;
	use App\Models\EstruturaNivel;
	use App\Support\Datas;
	use App\Support\Dinheiro;

	$andamento = $jogo->status === StatusJogo::EmAndamento;
	$torneio = $jogo->eTorneio();
	$intervalo = (bool) $relogio?->noIntervalo();
	$depois = $relogio?->proximo();

	// O que vai grande: título, blinds e ante. No intervalo, os blinds da volta.
	if ($relogio && $intervalo) {
		$volta = $depois && ! $depois->e_intervalo ? $depois : null;
		[$titulo, $rotuloBlinds, $blinds, $ante] = [$relogio->titulo(), $volta ? 'Na volta · nível '.(($relogio->ultimoNumero ?? 0) + 1) : null, $volta?->blinds(), $volta?->ante];
	} elseif ($relogio) {
		[$titulo, $rotuloBlinds, $blinds, $ante] = [$relogio->titulo(), 'Blinds', $relogio->nivel()->blinds(), $relogio->nivel()->ante];
	} else {
		[$titulo, $rotuloBlinds, $blinds, $ante] = [$jogo->nivel_atual ? 'Nível '.$jogo->nivel_atual : 'Blinds', null, $jogo->blinds_atual, null];
	}

	$proximo = match (true) {
		! $relogio || $intervalo => null,
		! $depois => 'Último nível da estrutura',
		$depois->e_intervalo => 'Depois: '.$depois->blinds().' · '.$depois->duracao_minutos.' min',
		default => 'Próximo: nível '.(($relogio->ultimoNumero ?? 0) + 1).' · '.$depois->blindsComAnte(),
	};
	$situacao = match (true) {
		! $relogio || $relogio->rodando => null,
		$relogio->acabou() => 'Fim da estrutura',
		! $relogio->iniciado => 'Aguardando o início',
		default => 'Pausado',
	};
	$intervaloEm = $relogio && ! $intervalo ? $relogio->intervaloEm() : null;
	$fase = $torneio ? $jogo->fase?->label() : 'Cash game';
@endphp

<div
	class="rh-tv"
	wire:poll.5s.keep-alive
	x-data="tv"
	data-nivel="{{ $relogio?->nivel()->id }}"
	x-on:mousemove.window.throttle.500ms="mexeu()"
	x-on:pointerdown.window="mexeu(); destravar()"
	x-on:keydown.window="mexeu(); destravar()"
	x-on:relogio-zerou="trocou()"
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
		<main @class(['rh-tv__palco', 'sem-lado' => ! $torneio])>
			<section @class(['rh-tv__relogio', 'is-intervalo' => $intervalo, 'sem-relogio' => ! $relogio])>
				<div class="rh-tv__titulo">{{ $titulo }}</div>
				@if ($relogio)
					<div @class(['rh-tv__tempo', 'is-longo' => $relogio->restante >= 3600])><x-site.poker.relogio :estado="$relogio" /></div>
					@if ($situacao)
						<div class="rh-tv__situacao">{{ $situacao }}</div>
					@endif
				@endif
				@if ($blinds)
					<div class="rh-tv__blinds">
						@if ($rotuloBlinds)
							<span class="rh-tv__rotulo">{{ $rotuloBlinds }}</span>
						@endif
						<span class="v">{{ $blinds }}</span>
						@if ($ante)
							<span class="rh-tv__ante">Ante {{ EstruturaNivel::fichas($ante) }}</span>
						@endif
					</div>
				@endif
				@if ($proximo)
					<div class="rh-tv__proximo">{{ $proximo }}</div>
				@endif
			</section>

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
			@if ($intervaloEm !== null)
				<div><span class="rh-label">Intervalo em</span><span class="v"><x-site.poker.relogio :estado="$relogio" :segundos="$intervaloEm - $relogio->fracao" /></span></div>
			@endif
		</footer>
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
