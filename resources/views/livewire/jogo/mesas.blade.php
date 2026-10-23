{{-- Tela 06: configuração das mesas (docs/prototipo, tela 06). --}}
@php
	$torneio = $jogo->eTorneio();
	$podeOrganizar = auth('web')->user()->can('mesas.organizar');
	$podeIniciar = auth('web')->user()->can('jogo.fases');
	$sentados = $mesas->sum(fn ($m) => $m->participantes->count());
	$confirmacao = 'Iniciar '.($torneio ? 'o torneio' : 'o jogo').' com '.$sentados.' '.($sentados === 1 ? 'jogador sentado' : 'jogadores sentados').'?'
		.($semLugar->isNotEmpty() ? ' '.$semLugar->count().' sem lugar '.($semLugar->count() === 1 ? 'continua' : 'continuam').' esperando.' : '');
@endphp

<div class="rh-grid rh-mesas">
	<aside class="rh-col rh-mesas__lado">
		<div>
			<div class="rh-label">Antes do {{ $torneio ? 'torneio' : 'jogo' }} · 3 de 3</div>
			<h1 class="rh-title">Mesas</h1>
		</div>

		<div class="rh-panel">
			<div class="rh-panel__hd" style="min-height:44px">
				<span class="rh-label">Sem lugar</span>
				<span class="rh-spacer"></span>
				<span class="rh-num" style="font-weight:700">{{ $semLugar->count() }}</span>
			</div>
			<div class="rh-panel__bd rh-mesas__espera">
				@forelse ($semLugar as $participante)
					<button
						type="button"
						wire:key="espera-{{ $participante->id }}"
						@class(['rh-pcard', 'is-on' => $selecionado === $participante->id])
						@if ($podeOrganizar) wire:click="selecionar({{ $participante->id }})" @else disabled @endif
					>
						<x-site.props.avatar :nome="$participante->usuario->nome_exibicao" :foto="$participante->usuario->foto" tamanho="sm" />
						<span class="rh-pcard__n">{{ $participante->usuario->nome_exibicao }}</span>
						@svg('rh-move', 'rh-faint')
					</button>
				@empty
					<span class="rh-small rh-muted">Todos os confirmados têm lugar.</span>
				@endforelse

				@if ($podeOrganizar && $selecionadoSentado)
					<button type="button" class="rh-btn rh-btn--sm rh-btn--block" wire:click="semLugar">@svg('rh-out') Tirar {{ $selecionadoNome }} do lugar</button>
				@endif
			</div>
		</div>

		@if ($podeOrganizar)
			<button type="button" class="rh-btn rh-btn--block" wire:click="sortear" wire:confirm="Sortear de novo o lugar de todos os confirmados?" wire:loading.attr="disabled">@svg('rh-rotate') Sortear lugares</button>

			@if ($mesasLivres->isNotEmpty())
				<div class="rh-row" style="flex-wrap:nowrap">
					<select class="rh-select" wire:model="mesaNova" aria-label="Mesa para adicionar" style="flex:1;min-width:0">
						<option value="">Adicionar mesa…</option>
						@foreach ($mesasLivres as $mesa)
							<option value="{{ $mesa->id }}">{{ $mesa->nome }} ({{ $mesa->lugares }} lugares)</option>
						@endforeach
					</select>
					<button type="button" class="rh-btn rh-btn--icon" wire:click="adicionarMesa" aria-label="Adicionar a mesa escolhida">@svg('rh-plus')</button>
				</div>
			@endif

			<div class="rh-mesas__dica">
				@svg('rh-move')
				<span>
					@if ($selecionadoNome)
						<b>{{ $selecionadoNome }}</b> selecionado: clique num lugar vazio para mover ou num jogador para trocar.
					@else
						Clique num jogador e depois em outro lugar para trocar ou mover.
					@endif
				</span>
			</div>
		@endif

		<span class="rh-spacer"></span>

		@if ($podeIniciar)
			<button type="button" class="rh-btn rh-btn--primary rh-btn--lg rh-btn--block" wire:click="iniciar" wire:confirm="{{ $confirmacao }}" wire:loading.attr="disabled">
				Iniciar {{ $torneio ? 'torneio' : 'jogo' }}
			</button>
		@endif
	</aside>

	<div class="rh-col" style="gap:16px;min-width:0">
		@if ($erro)
			<div class="rh-warn" role="alert">@svg('rh-alert')<div>{{ $erro }}</div></div>
		@endif

		@forelse ($mesas as $jogoMesa)
			@php
				$porLugar = $jogoMesa->participantes->keyBy('lugar');
			@endphp
			<section class="rh-panel rh-mesas__mesa" wire:key="mesa-{{ $jogoMesa->id }}">
				<div class="rh-panel__hd">
					<h3 class="rh-heading">{{ $jogoMesa->mesa->nome }}</h3>
					<span class="rh-badge rh-badge--plain">{{ $porLugar->count() }}/{{ $jogoMesa->lugares }}</span>
					<span class="rh-spacer"></span>

					@if ($podeOrganizar)
						<label class="rh-mesas__ajuste">
							<span class="rh-small rh-muted">Lugares</span>
							<select class="rh-select" wire:change="ajustarLugares({{ $jogoMesa->id }}, $event.target.value)">
								@foreach (range(2, 12) as $n)
									<option value="{{ $n }}" @selected($n === $jogoMesa->lugares)>{{ $n }}</option>
								@endforeach
							</select>
						</label>
						<label class="rh-mesas__ajuste">
							<span class="rh-small rh-muted">Dealer</span>
							<select class="rh-select" wire:change="ajustarDealer({{ $jogoMesa->id }}, $event.target.value)">
								<option value="">Sem dealer</option>
								@foreach ($dealers as $dealer)
									<option value="{{ $dealer->id }}" @selected($dealer->id === $jogoMesa->dealer_id)>{{ $dealer->nome_exibicao }}</option>
								@endforeach
							</select>
						</label>
					@else
						<span class="rh-small rh-muted">Dealer: {{ $jogoMesa->dealer?->nome_exibicao ?? '—' }}</span>
					@endif

					<button type="button" class="rh-btn rh-btn--ghost rh-btn--icon rh-btn--sm" x-on:click="$dispatch('girar-mesa', {{ $jogoMesa->id }})" aria-label="Girar a {{ $jogoMesa->mesa->nome }}" title="Girar a mesa">@svg('rh-rotate')</button>

					@if ($podeOrganizar && $porLugar->isEmpty())
						<button type="button" class="rh-btn rh-btn--ghost rh-btn--icon rh-btn--sm" wire:click="removerMesa({{ $jogoMesa->id }})" wire:confirm="Tirar a {{ $jogoMesa->mesa->nome }} deste jogo?" aria-label="Tirar a {{ $jogoMesa->mesa->nome }} do jogo" title="Tirar do jogo">@svg('rh-x')</button>
					@endif
				</div>

				<div class="rh-mesas__feltro">
					<x-site.poker.mesa
						wire:key="mesa-{{ $jogoMesa->id }}-{{ $jogoMesa->lugares }}"
						:jogo-mesa="$jogoMesa"
						:ocupantes="$porLugar"
						:selecionado="$selecionado"
						:acao="$podeOrganizar ? 'clicarAssento' : null"
						compacta
						:ar="2.6"
						:ar-retrato="0.72"
						:rx="44"
						:ry="36"
					/>
				</div>
			</section>
		@empty
			<div class="rh-panel"><div class="rh-panel__bd rh-muted">
				Nenhuma mesa neste jogo ainda.
				@if ($podeOrganizar) Adicione uma pela lateral. @endif
			</div></div>
		@endforelse
	</div>
</div>
