{{--
	v3: tela do relógio de quem controla o torneio (Livewire Jogo\ControleDoRelogio). O relógio grande é o
	mesmo painel do link (motor relogio-torneio.js, que anda sozinho); os botões grandes mandam no relógio
	e o AVISO toca o som em todas as telas do link (e aqui, no clique). Celular em pé: relógio em cima e
	botões embaixo; deitado: relógio à esquerda e botões à direita.
--}}
@php
	$link = $jogo->linkDoRelogio();
	$comandoPlay = $relogio && ! $relogio->acabou() ? ($relogio->rodando ? 'pausar' : 'continuar') : null;
	$rotuloPlay = match (true) {
		$comandoPlay === 'pausar' => 'PAUSAR',
		(bool) $relogio?->iniciado => 'CONTINUAR',
		default => 'INICIAR',
	};
	$intervaloEm = $relogio?->intervaloEm();
@endphp

<div
	class="rh-controle"
	wire:poll.5s.keep-alive
	x-data="relogioTorneio"
	x-init="$manterAcesa()"
	data-aviso="{{ json_encode($jogo->avisoParaNavegador()) }}"
	@if ($relogio) data-relogio="{{ json_encode($relogio->paraNavegador()) }}" @endif
>
	<header class="rh-controle__topo">
		<a class="rh-btn rh-btn--ghost" href="{{ route('jogos.ao-vivo', $jogo) }}">@svg('rh-chev-l') <span class="rh-controle__texto">AO VIVO</span></a>
		<div class="rh-controle__jogo">
			<b>{{ $jogo->nome }}</b>
			<span>Relógio{{ $jogo->eTorneio() && $jogo->fase ? ' · '.$jogo->fase->label() : '' }}</span>
		</div>
		<span class="rh-spacer"></span>
		<button type="button" class="rh-btn" x-data="{ copiado: false }" x-on:click="$copiar(@js($link)).then(() => { copiado = true; setTimeout(() => copiado = false, 2500) })" title="Copiar o link do relógio para a TV e outras telas">
			@svg('rh-copy') <span class="rh-controle__texto" x-text="copiado ? 'COPIADO' : 'LINK DA TV'">LINK DA TV</span>
		</button>
	</header>

	@if ($erro)
		<div class="rh-warn" role="alert">@svg('rh-alert')<div>{{ $erro }}</div></div>
	@elseif ($mensagem)
		<div class="rh-warn rh-warn--ok" role="status" wire:key="mensagem-{{ md5($mensagem.now()->timestamp) }}">@svg('rh-check')<div>{{ $mensagem }}</div></div>
	@endif

	<div @class(['rh-controle__corpo', 'sem-botoes' => ! $pode])>
		<div class="rh-controle__relogio">
			@if ($relogio)
				<x-site.poker.painel-relogio :estado="$relogio" />
				<div class="rh-controle__intervalo" wire:ignore x-show="intervaloEm !== null" @if ($intervaloEm === null) style="display:none" @endif>
					Intervalo em <span x-text="niveisAteIntervalo"></span> · <x-site.poker.relogio :estado="$relogio" :segundos="($intervaloEm ?? 0) - $relogio->fracao" />
				</div>
			@else
				{{-- Sem estrutura de blinds: o nível é digitado na tela ao vivo; aqui fica só o AVISO. --}}
				<section class="rh-tv__relogio sem-relogio" x-bind:class="{ 'is-aviso': avisando }">
					<span class="rh-tv__aviso" x-show="avisando" x-cloak>AVISO</span>
					<div class="rh-tv__titulo">{{ $jogo->nivel_atual ? 'Nível '.$jogo->nivel_atual : 'Blinds' }}</div>
					<div class="rh-tv__blinds"><span class="v">{{ $jogo->blinds_atual ?? '—' }}</span></div>
					<div class="rh-tv__proximo">Sem estrutura de blinds: o nível é digitado na tela ao vivo.</div>
				</section>
			@endif
		</div>

		@if ($pode)
			<div class="rh-controle__comandos">
				@if ($relogio)
					<div class="rh-controle__principais">
						<button type="button" class="rh-btn rh-btn--lg" wire:click="controlar('anterior')" wire:loading.attr="disabled" @disabled(! $relogio->anterior())>@svg('rh-chev-l') ANTERIOR</button>
						@if ($comandoPlay)
							<button type="button" class="rh-btn rh-btn--primary rh-btn--lg rh-controle__play" wire:click="controlar('{{ $comandoPlay }}')" wire:loading.attr="disabled">@svg($comandoPlay === 'pausar' ? 'rh-pause' : 'rh-play') {{ $rotuloPlay }}</button>
						@else
							<button type="button" class="rh-btn rh-btn--lg rh-controle__play" disabled>FIM</button>
						@endif
						<button type="button" class="rh-btn rh-btn--lg" wire:click="controlar('proximo')" wire:loading.attr="disabled" @disabled(! $relogio->proximo())>PRÓXIMO @svg('rh-chev-r')</button>
					</div>
					<div class="rh-controle__ajustes" role="group" aria-label="Ajustar o tempo do nível">
						@foreach ([-5, -1, 1, 5] as $minutos)
							<button type="button" class="rh-btn" wire:key="ajuste-{{ $minutos }}" wire:click="controlar('ajustar', {{ $minutos }})" wire:loading.attr="disabled">{{ ($minutos > 0 ? '+' : '−').abs($minutos) }} min</button>
						@endforeach
					</div>
				@endif
				<button type="button" class="rh-btn rh-btn--danger rh-btn--lg rh-controle__aviso" x-on:click="$tocar('aviso')" wire:click="avisar" wire:loading.attr="disabled" title="Toca o som de aviso em todas as telas do link">
					@svg('rh-alert') AVISO
				</button>
			</div>
		@else
			<p class="rh-controle__so-ver rh-small rh-muted">Só quem controla o relógio pausa, troca de nível e dispara o aviso.</p>
		@endif
	</div>
</div>
