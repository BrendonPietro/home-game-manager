{{-- Tela 13: resultado e posições (docs/prototipo, tela 13). Torneio: posições; cash game: cash-outs. --}}
@php
	use App\Enums\StatusParticipante;
	use App\Support\Dinheiro;

	$torneio = $jogo->eTorneio();
	$podeDefinir = auth('web')->user()->can('jogo.fases');
	$pct = fn ($percentual): string => str_replace('.', ',', (string) (float) $percentual).'%';
	$premiadas = $podio->count();
	$mudancas = count(array_filter($posicoes, fn ($p) => $p !== ''));
@endphp

<div class="rh-col rh-resultado" style="gap:16px">
	<div class="rh-row">
		<div>
			<div class="rh-label">Final{{ $jogo->estaFinalizado() ? ' · finalizado' : '' }}</div>
			<h1 class="rh-title">{{ $torneio ? 'Resultado e posições' : 'Resultado' }}</h1>
		</div>
		<span class="rh-spacer"></span>
		@if ($urlFechamento)
			<a class="rh-btn rh-btn--primary rh-btn--lg" href="{{ $urlFechamento }}">{{ $jogo->estaFinalizado() ? 'VER FECHAMENTO' : 'IR PARA FECHAMENTO' }} @svg('rh-chev-r')</a>
		@endif
	</div>

	@if ($aviso)
		<div class="rh-warn rh-warn--ok" role="status">@svg('rh-check')<div>{{ $aviso }}</div></div>
	@endif
	@if ($erro)
		<div class="rh-warn" role="alert">@svg('rh-alert')<div>{{ $erro }}</div></div>
	@endif

	{{-- Pódio: as posições premiadas e quem está nelas. --}}
	@if ($podio->isNotEmpty())
		<div @class(['rh-grid', 'rh-grid--'.min(max($premiadas, 2), 4)])>
			@foreach ($podio as $lugar)
				<div @class(['rh-stat', 'rh-resultado__podio', 'is-primeiro' => $lugar['posicao'] === 1])>
					<span class="rh-label">{{ $lugar['posicao'] }}º lugar · {{ $pct($lugar['percentual']) }}</span>
					<div class="rh-row rh-resultado__quem">
						@if ($lugar['quem'])
							<x-site.props.avatar :nome="$lugar['quem']->nome()" :foto="$lugar['quem']->usuario->foto" />
							<b>{{ $lugar['quem']->nome() }}</b>
						@else
							<span class="rh-faint">a definir</span>
						@endif
						<span class="rh-spacer"></span>
						<span class="rh-stat__v">@brl($lugar['valor'])</span>
					</div>
				</div>
			@endforeach
		</div>
	@endif

	<section class="rh-panel">
		<div class="rh-panel__hd rh-resultado__hd">
			<h3 class="rh-heading">{{ $torneio ? 'Posições' : 'Cash-outs' }}</h3>
			<span class="rh-small rh-muted">
				@if (! $torneio)
					Do maior lucro para o menor. Clique em quem ainda está na mesa para registrar o cash-out.
				@elseif ($emJogo->isNotEmpty())
					Quem caiu já tem a posição. Escolha a de quem ainda está em jogo (do 1º ao {{ $emJogo->count() }}º).
				@else
					As posições saem da ordem das eliminações.
				@endif
			</span>
		</div>

		<div class="rh-resultado__tabela">
			<table class="rh-table">
				<thead>
					<tr>
						<th style="width:120px">Posição</th>
						<th>Jogador</th>
						<th>Mesa</th>
						<th class="n">Investido</th>
						<th class="n">{{ $torneio ? 'Prêmio' : 'Cash-out' }}</th>
						<th class="n">Resultado</th>
					</tr>
				</thead>
				<tbody>
					@foreach ($linhas as ['participante' => $participante, 'posicao' => $posicao])
						@php
							$conta = $contas[$participante->id];
							$definido = $posicao !== null;
							$escolhe = $podeDefinir && $emJogo->contains('id', $participante->id);
						@endphp
						<tr wire:key="resultado-{{ $participante->id }}" @class(['rh-resultado__linha', 'is-selected' => $selecionado === $participante->id]) wire:click="selecionar({{ $participante->id }})">
							<td>
								@if ($escolhe)
									<select class="rh-select rh-resultado__select" wire:model.live="posicoes.{{ $participante->id }}" wire:click.stop aria-label="Posição de {{ $participante->nome() }}">
										<option value="">—</option>
										@foreach (range(1, $emJogo->count()) as $opcao)
											<option value="{{ $opcao }}">{{ $opcao }}º</option>
										@endforeach
									</select>
								@elseif ($definido)
									<span class="rh-resultado__posicao">{{ $posicao }}º</span>
								@else
									<span class="rh-faint">{{ $torneio ? 'em jogo' : 'na mesa' }}</span>
								@endif
							</td>
							<td>
								<span class="rh-who">
									<x-site.props.avatar :nome="$participante->nome()" :foto="$participante->usuario->foto" tamanho="sm" />
									{{ $participante->nome() }}
									@if ($torneio && $conta->premio > 0)
										<span class="rh-badge rh-badge--ok rh-badge--plain">ITM</span>
									@endif
								</span>
							</td>
							<td class="rh-muted">{{ $participante->jogoMesa?->nome ?? '—' }}</td>
							<td class="n">{{ Dinheiro::brlCentavos($conta->investido) }}</td>
							<td class="n">{!! $conta->premio ? e(Dinheiro::brlCentavos($conta->premio)) : '<span class="rh-faint">—</span>' !!}</td>
							<td @class(['n', 'rh-pos' => $definido && $conta->resultado() > 0, 'rh-neg' => $definido && $conta->resultado() < 0])>
								{!! $definido ? e(Dinheiro::brlCentavos($conta->resultado(), true)) : '<span class="rh-faint">pendente</span>' !!}
							</td>
						</tr>
					@endforeach
				</tbody>
			</table>
		</div>

		@if ($emJogo->isNotEmpty() && $podeDefinir)
			<div class="rh-resultado__acao">
				<span class="rh-small rh-muted">{{ $mudancas }} de {{ $emJogo->count() }} {{ $emJogo->count() === 1 ? 'posição escolhida' : 'posições escolhidas' }}. Ao gravar, cada um vira uma eliminação definitiva, do último para o 2º.</span>
				<span class="rh-spacer"></span>
				<button type="button" class="rh-btn rh-btn--primary" wire:click="salvar" wire:confirm="Gravar o resultado de quem está em jogo?" wire:loading.attr="disabled" @disabled($mudancas !== $emJogo->count())>@svg('rh-check') GRAVAR RESULTADO</button>
			</div>
		@endif
	</section>

	@if ($selecionado)
		<livewire:jogo.acoes-jogador :jogo="$jogo" :participante-id="$selecionado" :muda-lugar="false" :key="'acoes-'.$selecionado" />
	@endif
</div>
