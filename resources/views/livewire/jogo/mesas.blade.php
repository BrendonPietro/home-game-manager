{{-- Tela 06: configuração das mesas (docs/prototipo, tela 06). --}}
@php
	$torneio = $jogo->eTorneio();
	$podeOrganizar = auth('web')->user()->can('mesas.organizar');
	$podeIniciar = auth('web')->user()->can('jogo.fases');
	$sentados = $mesas->sum(fn ($m) => $m->participantes->count());
	$confirmacao = 'Iniciar '.($torneio ? 'o torneio' : 'o jogo').' com '.$sentados.' '.($sentados === 1 ? 'jogador sentado' : 'jogadores sentados').'?'
		.($semLugar->isNotEmpty() ? ' '.$semLugar->count().' sem lugar '.($semLugar->count() === 1 ? 'continua' : 'continuam').' esperando.' : '')
		.($torneio ? ' Cada sentado já entra com o buy-in.' : '');
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
				<span class="rh-num" style="font-weight:500">{{ $semLugar->count() }}</span>
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
						@svg('rh-mover', 'rh-faint')
					</button>
				@empty
					<span class="rh-small rh-muted">Todos os confirmados têm lugar</span>
				@endforelse

				@if ($podeOrganizar && $selecionadoSentado)
					<button type="button" class="rh-btn rh-btn--sm rh-btn--block" wire:click="semLugar">@svg('rh-sair') Tirar {{ $selecionadoNome }} do lugar</button>
				@endif
			</div>
		</div>

		@if ($podeOrganizar)
			<button type="button" class="rh-btn rh-btn--block" x-on:click="$dispatch('abrir-sorteio')">@svg('rh-girar') Sortear lugares</button>

			<button type="button" class="rh-btn rh-btn--block" wire:click="adicionarMesa" wire:loading.attr="disabled">@svg('rh-adicionar') Adicionar mesa</button>

			<div class="rh-mesas__dica">
				@svg('rh-mover')
				<span>
					@if ($selecionadoNome)
						<b>{{ $selecionadoNome }}</b> selecionado: clique num lugar vazio para mover ou num jogador para trocar.
					@else
						Clique num lugar vazio para escolher quem senta, ou num jogador e depois em outro lugar para trocar ou mover.
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
		@if ($erro && ! $quemSenta)
			<div class="rh-warn" role="alert">@svg('rh-alerta')<div>{{ $erro }}</div></div>
		@endif

		@forelse ($mesas as $jogoMesa)
			@php
				$porLugar = $jogoMesa->participantes->keyBy('lugar');
			@endphp
			<section class="rh-panel rh-mesas__mesa" wire:key="mesa-{{ $jogoMesa->id }}">
				<div class="rh-panel__hd">
					<h3 class="rh-heading">{{ $jogoMesa->nome }}</h3>
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

					<button type="button" class="rh-btn rh-btn--ghost rh-btn--icon rh-btn--sm" x-on:click="$dispatch('girar-mesa', {{ $jogoMesa->id }})" aria-label="Girar a {{ $jogoMesa->nome }}" title="Girar a mesa">@svg('rh-girar')</button>

					@if ($podeOrganizar && $porLugar->isEmpty())
						<button type="button" class="rh-btn rh-btn--ghost rh-btn--icon rh-btn--sm" wire:click="removerMesa({{ $jogoMesa->id }})" wire:confirm="Tirar a {{ $jogoMesa->nome }} deste jogo?" aria-label="Tirar a {{ $jogoMesa->nome }} do jogo" title="Tirar do jogo">@svg('rh-fechar')</button>
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

	@include('livewire.jogo.partials.quem-senta')

	{{-- Modal Jogadores: marcar quem chegou (ou todos), as mesas, e sortear. --}}
	@if ($podeOrganizar)
		<livewire:jogo.sorteio :jogo="$jogo" :key="'sorteio-'.$jogo->id" />
	@endif
</div>
