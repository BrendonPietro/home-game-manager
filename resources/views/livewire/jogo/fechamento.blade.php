{{-- Tela 14: fechamento financeiro (docs/prototipo, tela 14). --}}
@php
	use App\Enums\FormaPagamento;
	use App\Support\Dinheiro;
	use App\Support\EtapaDoJogo;

	$torneio = $jogo->eTorneio();
	$finalizado = $jogo->estaFinalizado();
	$usuario = auth('web')->user();
	$nome = fn ($participante): string => $participante?->nome() ?? 'Caixa';
	$texto = 'Acertos · '.$jogo->nome."\n".$acertos
		->map(fn ($a) => $nome($a['de']).' → '.$nome($a['para']).': '.Dinheiro::brlCentavos($a['valor']).($a['pago'] ? ' (feito)' : ''))
		->join("\n");
	$abertos = $linhas->reject(fn ($l) => $l['conta']->quitado())->count();
	$urlResultado = EtapaDoJogo::url($jogo, 'resultado');
	$oQue = $torneio ? 'torneio' : 'jogo';
@endphp

<div class="rh-col rh-fechamento" style="gap:16px">
	<div class="rh-row">
		<div>
			<div class="rh-label">Final · {{ $finalizado ? 'finalizado às '.$jogo->finalizado_em->format('H:i') : 'última etapa' }}</div>
			<h1 class="rh-title">Fechamento financeiro</h1>
		</div>
		<span class="rh-spacer"></span>
		@if ($acertos->isNotEmpty())
			<button type="button" class="rh-btn" x-data="{ copiado: false }" x-on:click="$copiar(@js($texto)).then(() => { copiado = true; setTimeout(() => copiado = false, 2000) })">
				@svg('rh-copy') <span x-text="copiado ? 'Copiado!' : 'Copiar acertos'">Copiar acertos</span>
			</button>
		@endif
	</div>

	@if ($aviso)
		<div class="rh-warn rh-warn--ok" role="status">@svg('rh-check')<div>{{ $aviso }}</div></div>
	@endif
	@if ($erro && ! $confirmando)
		<div class="rh-warn" role="alert">@svg('rh-alert')<div>{{ $erro }}</div></div>
	@endif

	<div class="rh-grid rh-grid--4">
		<div class="rh-stat"><span class="rh-label">Total investido</span><span class="rh-stat__v">{{ Dinheiro::brlCentavos($resumo->arrecadacao) }}</span></div>
		<div class="rh-stat"><span class="rh-label">{{ $torneio ? 'Prêmios' : 'Cash-outs' }}</span><span class="rh-stat__v">{{ Dinheiro::brlCentavos($premios) }}</span></div>
		<div class="rh-stat"><span class="rh-label">Fica com a casa</span><span class="rh-stat__v">{{ Dinheiro::brlCentavos($retido) }}</span><span class="rh-stat__s">taxas{{ $resumo->foraDaPremiacao ? ' + fora da premiação' : '' }}</span></div>
		<div class="rh-stat">
			<span class="rh-label">Conferência</span>
			@if ($diferenca === 0)
				<span class="rh-stat__v rh-pos rh-fechamento__confere">@svg('rh-check') R$ 0 de diferença</span>
			@else
				<span class="rh-stat__v rh-neg">{{ Dinheiro::brlCentavos(abs($diferenca)) }}</span>
				<span class="rh-stat__s">{{ $torneio ? 'do prize pool ainda sem dono' : ($diferenca > 0 ? 'a mais na premiação do que nos cash-outs' : 'a mais nos cash-outs do que na premiação') }}</span>
			@endif
		</div>
	</div>

	@if ($faltam->isNotEmpty())
		<div class="rh-warn" role="alert">
			@svg('rh-alert')
			<div>
				{{ ($torneio ? 'Falta a posição de ' : 'Falta o cash-out de ').$faltam->join(', ', ' e ').'.' }}
				@if ($torneio && $urlResultado)
					<a class="rh-link" href="{{ $urlResultado }}">Definir no resultado</a>
				@endif
			</div>
		</div>
	@endif

	<div class="rh-grid rh-fechamento__grade">
		<section class="rh-panel">
			<div class="rh-panel__hd">
				<h3 class="rh-heading">Por jogador</h3>
				<span class="rh-spacer"></span>
				<span class="rh-small rh-muted">{{ $abertos === 0 ? 'todos quitados' : $abertos.' com saldo em aberto' }}</span>
			</div>
			<div class="rh-fechamento__tabela">
				<table class="rh-table">
					<thead>
						<tr>
							<th>Jogador</th>
							<th class="n">Investiu</th>
							<th class="n">{{ $torneio ? 'Premiação' : 'Cash-out' }}</th>
							<th class="n">Resultado</th>
							<th class="n">Saldo</th>
						</tr>
					</thead>
					<tbody>
						@foreach ($linhas as ['participante' => $participante, 'conta' => $conta])
							<tr wire:key="fechamento-{{ $participante->id }}" @class(['rh-fechamento__linha', 'is-selected' => $selecionado === $participante->id]) wire:click="selecionar({{ $participante->id }})">
								<td><span class="rh-who"><x-site.props.avatar :nome="$participante->nome()" :foto="$participante->usuario->foto" tamanho="sm" />{{ $participante->nome() }}</span></td>
								<td class="n">{{ Dinheiro::brlCentavos($conta->investido) }}</td>
								<td class="n">{!! $conta->premio ? e(Dinheiro::brlCentavos($conta->premio)) : '<span class="rh-faint">R$ 0</span>' !!}</td>
								<td @class(['n', 'rh-fechamento__resultado', 'rh-pos' => $conta->resultado() > 0, 'rh-neg' => $conta->resultado() < 0])>{{ Dinheiro::brlCentavos($conta->resultado(), true) }}</td>
								<td @class(['n', 'rh-neg' => $conta->saldo > 0, 'rh-pos' => $conta->saldo < 0, 'rh-faint' => $conta->quitado()])>
									{{ $conta->saldo > 0 ? 'deve '.Dinheiro::brlCentavos($conta->saldo) : ($conta->saldo < 0 ? 'recebe '.Dinheiro::brlCentavos(-$conta->saldo) : 'quitado') }}
								</td>
							</tr>
						@endforeach
					</tbody>
					<tfoot>
						<tr>
							<td>Total</td>
							<td class="n">{{ Dinheiro::brlCentavos($resumo->arrecadacao) }}</td>
							<td class="n">{{ Dinheiro::brlCentavos($premios) }}</td>
							<td class="n rh-neg">{{ Dinheiro::brlCentavos($premios - $resumo->arrecadacao, true) }} <span class="rh-small rh-muted">(casa)</span></td>
							<td></td>
						</tr>
					</tfoot>
				</table>
			</div>
		</section>

		<div class="rh-col" style="gap:16px">
			<section class="rh-panel">
				<div class="rh-panel__hd rh-fechamento__hd">
					<h3 class="rh-heading">Acertos</h3>
					<span class="rh-small rh-muted">quem paga quem · {{ $acertos->count() }} {{ $acertos->count() === 1 ? 'transferência' : 'transferências' }}{{ $finalizado ? '' : ' (sugestão)' }}</span>
					@if ($finalizado && $usuario->can('pagamentos.registrar') && $acertos->contains('pago', false))
						<span class="rh-spacer"></span>
						<label class="rh-fechamento__forma">
							<span class="rh-small rh-muted">Feito por</span>
							<select class="rh-select" wire:model="forma">
								@foreach (FormaPagamento::cases() as $opcao)
									<option value="{{ $opcao->value }}">{{ $opcao->label() }}</option>
								@endforeach
							</select>
						</label>
					@endif
				</div>

				@forelse ($acertos as $acerto)
					<div @class(['rh-xfer', 'rh-fechamento__acerto', 'is-feito' => $acerto['pago']]) wire:key="acerto-{{ $acerto['id'] ?? $loop->index }}">
						<span class="rh-who">
							@if ($acerto['de'])
								<x-site.props.avatar :nome="$acerto['de']->nome()" :foto="$acerto['de']->usuario->foto" tamanho="sm" />{{ $acerto['de']->nome() }}
							@else
								<span class="rh-avatar rh-avatar--sm rh-avatar--casa" aria-hidden="true">♠</span>Caixa
							@endif
						</span>
						<span class="rh-xfer__arrow">@svg('rh-arrow-r')</span>
						<span class="rh-who">
							@if ($acerto['para'])
								<x-site.props.avatar :nome="$acerto['para']->nome()" :foto="$acerto['para']->usuario->foto" tamanho="sm" />{{ $acerto['para']->nome() }}
							@else
								<span class="rh-avatar rh-avatar--sm rh-avatar--casa" aria-hidden="true">♠</span>Caixa
							@endif
						</span>
						<span class="rh-xfer__v">{{ Dinheiro::brlCentavos($acerto['valor']) }}</span>
						@if ($finalizado)
							<span class="rh-fechamento__feito">
								@if ($acerto['pago'])
									<span class="rh-badge rh-badge--ok">feito</span>
								@elseif ($usuario->can('pagamentos.registrar'))
									<button type="button" class="rh-btn rh-btn--sm" wire:click="marcarAcerto({{ $acerto['id'] }})" wire:confirm="{{ $nome($acerto['de']).' → '.$nome($acerto['para']).': '.Dinheiro::brlCentavos($acerto['valor']) }} feito?" wire:loading.attr="disabled">@svg('rh-check') FEITO</button>
								@endif
							</span>
						@endif
					</div>
				@empty
					<div class="rh-panel__bd rh-muted">Ninguém deve nada a ninguém.</div>
				@endforelse
			</section>

			@if (! $finalizado && $usuario->can('jogo.finalizar'))
				<button type="button" class="rh-btn rh-btn--primary rh-btn--lg rh-btn--block rh-fechamento__finalizar" wire:click="abrirFinalizar">@svg('rh-check') FINALIZAR {{ mb_strtoupper($oQue) }}</button>
				<div class="rh-small rh-muted" style="text-align:center">Ao finalizar, o {{ $oQue }} fica somente leitura (menos o pagamento de pendências) e entra nos relatórios.</div>
			@elseif ($finalizado)
				<div class="rh-small rh-muted">Saldo em aberto é pendência do jogador: clique nele para registrar o pagamento, ou marque o acerto como feito.</div>
			@endif
		</div>
	</div>

	@if ($confirmando)
		<div class="rh-scrim" wire:keydown.escape.window="fecharFinalizar">
			<div class="rh-modal" style="max-width:440px" role="dialog" aria-modal="true" aria-labelledby="finalizar-titulo">
				<div class="rh-modal__hd">
					<h2 class="rh-heading" id="finalizar-titulo" style="font-size:26px">Finalizar {{ $oQue }}</h2>
					<button type="button" class="rh-btn rh-btn--ghost rh-btn--icon rh-x" wire:click="fecharFinalizar" aria-label="Fechar">@svg('rh-x')</button>
				</div>
				<div class="rh-modal__bd rh-col" style="gap:14px">
					@if ($erro)
						<div class="rh-warn" role="alert">@svg('rh-alert')<div>{{ $erro }}</div></div>
					@endif
					<div class="rh-warn">
						@svg('rh-alert')
						<div>
							<b>Ao finalizar:</b>
							<ul>
								<li>o {{ $oQue }} fica somente leitura, menos o pagamento de pendências;</li>
								<li>{{ $acertos->count() }} {{ $acertos->count() === 1 ? 'acerto fica gravado' : 'acertos ficam gravados' }} para marcar como feitos;</li>
								<li>os pontos dos rankings do {{ $oQue }} são gravados;</li>
								<li>{{ $abertos === 0 ? 'ninguém fica com pendência.' : $abertos.' '.($abertos === 1 ? 'jogador fica' : 'jogadores ficam').' com saldo em aberto (pendência).' }}</li>
							</ul>
						</div>
					</div>
				</div>
				<div class="rh-modal__ft">
					<button type="button" class="rh-btn rh-btn--lg" wire:click="fecharFinalizar">VOLTAR</button>
					<button type="button" class="rh-btn rh-btn--primary rh-btn--lg" wire:click="finalizar" wire:loading.attr="disabled">@svg('rh-check') FINALIZAR</button>
				</div>
			</div>
		</div>
	@endif

	@if ($selecionado)
		<livewire:jogo.acoes-jogador :jogo="$jogo" :participante-id="$selecionado" :muda-lugar="false" :key="'acoes-'.$selecionado" />
	@endif
</div>
