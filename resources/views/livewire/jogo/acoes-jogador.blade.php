{{-- Telas 08, 08b e 09: painel do jogador na tela ao vivo (docs/prototipo, playerDrawer, buyinPicker, confirmModal). --}}
@php
	use App\Enums\DirecaoPagamento;
	use App\Enums\FaseTorneio;
	use App\Enums\FormaPagamento;
	use App\Enums\StatusParticipante;
	use App\Enums\TipoItem;
	use App\Models\Eliminacao;
	use App\Models\Entrada;
	use App\Models\Pagamento;
	use App\Support\Dinheiro;

	$torneio = $jogo->eTorneio();
	$quem = auth('web')->user();
	$centavos = fn ($linhas) => $linhas->sum(fn (Entrada $e) => Dinheiro::centavos($e->valor));
	$doTipo = fn (TipoItem $tipo) => $entradas->filter(fn (Entrada $e) => $e->tipo === $tipo);
	$buyin = $doTipo(TipoItem::Buyin)->first();
	$rebuys = $doTipo(TipoItem::Rebuy);
	$addons = $doTipo(TipoItem::Addon);
	$opcionaisComprados = $doTipo(TipoItem::Opcional);
	$fichas = fn (int $n): string => number_format($n, 0, ',', '.').' fichas';
	$fichaDa = fn (Entrada $e): array => match ($e->tipo) {
		TipoItem::Buyin => [$jogo->fichaDoBuyin($e->item), null],
		TipoItem::Rebuy => ['R', 'r'],
		TipoItem::Addon => ['A', 'a'],
		TipoItem::Opcional => [null, null],
	};
	$rotulo = fn (TipoItem $tipo): string => $tipo === TipoItem::Rebuy && ! $torneio ? 'Recompra' : $tipo->label();
	$local = $participante->jogoMesa ? $participante->jogoMesa->mesa->nome.' · Lugar '.$participante->lugar : 'Sem lugar';
	$situacao = match ($participante->status) {
		StatusParticipante::Eliminado => 'Eliminado'.($participante->posicao_final ? ' · '.$participante->posicao_final.'º' : ''),
		StatusParticipante::Saiu => 'Saiu (cash-out)',
		StatusParticipante::Confirmado => 'Confirmado',
		default => null,
	};
	// Desfazer e cash-out só com o jogo em andamento; finalizado, o painel serve para pagar pendências.
	$emAndamento = $jogo->status === \App\Enums\StatusJogo::EmAndamento;
	$podeDesfazer = $emAndamento && $quem->can('acoes.desfazer') && $ultima
		&& (! $ultima instanceof Entrada || $jogo->entradasAbertas());
@endphp

<div x-on:keydown.escape.window="$wire.modal ? $wire.fecharModal() : $dispatch('fechar-jogador')">
	<div class="rh-scrim rh-scrim--leve" wire:click="$dispatch('fechar-jogador')"></div>

	<aside class="rh-drawer rh-jogador" role="dialog" aria-modal="true" aria-label="Ações do jogador {{ $nome }}">
		<div class="rh-drawer__hd">
			<x-site.props.avatar :nome="$nome" :foto="$participante->usuario->foto" tamanho="lg" />
			<div style="min-width:0">
				<div class="rh-heading rh-jogador__nome">{{ $nome }}</div>
				<div class="rh-small rh-muted">{{ $local }}{{ $situacao ? ' · '.$situacao : '' }}</div>
			</div>
			<button type="button" class="rh-btn rh-btn--ghost rh-btn--icon rh-x" wire:click="$dispatch('fechar-jogador')" aria-label="Fechar">@svg('rh-x')</button>
		</div>

		<div class="rh-drawer__bd">
			@if ($aviso)
				<div class="rh-warn rh-warn--ok" role="status">@svg('rh-check')<div>{{ $aviso }}</div></div>
			@endif

			{{-- Entradas: buy-in, rebuy (recompra) e add-on conforme a fase. --}}
			@can('entradas.registrar')
				@if ($botoes)
					<div class="rh-jogador__acoes">
						@foreach ($botoes as $botao)
							@php $ficha = match ($botao['tipo']) { TipoItem::Buyin => ['B', null], TipoItem::Rebuy => ['R', 'r'], default => ['A', 'a'] }; @endphp
							<button type="button" class="rh-action" wire:click="abrirEntrada('{{ $botao['tipo']->value }}')" @disabled(! $botao['ok']) aria-label="{{ $rotulo($botao['tipo']) }}: {{ $botao['meta'] }}">
								<span class="rh-action__top"><span @class(['rh-chip', 'rh-chip--'.$ficha[1] => $ficha[1]])>{{ $ficha[0] }}</span>{{ $rotulo($botao['tipo']) }}</span>
								<span class="rh-action__meta">{{ $botao['meta'] }}</span>
							</button>
						@endforeach
					</div>
				@endif

				@if ($avulsos->isNotEmpty())
					<div class="rh-row">
						@foreach ($avulsos as $opcional)
							<button type="button" class="rh-btn rh-btn--sm" wire:click="abrirEntrada('opcional', {{ $opcional->id }})">@svg('rh-plus') {{ $opcional->nome }} · @brl($opcional->valor)</button>
						@endforeach
					</div>
				@endif
			@endcan

			{{-- O que ele comprou. --}}
			<div class="rh-jogador__blocos">
				<div class="rh-jogador__bloco">
					<span class="rh-chip rh-chip--lg">{{ $buyin ? $jogo->fichaDoBuyin($buyin->item) : 'B' }}</span>
					<div class="rh-jogador__bloco-t">
						<div class="rh-label">{{ $buyin ? $buyin->item->nome : 'Buy-in' }}</div>
						<div class="rh-small rh-muted">{{ $buyin ? $fichas($buyin->fichas) : 'Nenhum' }}</div>
					</div>
					<div class="rh-jogador__bloco-v">{{ $buyin ? Dinheiro::brl($buyin->valor) : '—' }}</div>
				</div>
				@if ($rebuys->isNotEmpty() || isset($botoes['rebuy']))
					<div class="rh-jogador__bloco">
						<span class="rh-chip rh-chip--r rh-chip--lg">R</span>
						<div class="rh-jogador__bloco-t">
							<div class="rh-label">{{ $torneio ? 'Rebuys' : 'Recompras' }} · {{ $rebuys->count() }}</div>
							<div class="rh-small rh-muted">{{ $rebuys->isNotEmpty() ? $fichas($rebuys->sum('fichas')) : 'Nenhum' }}</div>
						</div>
						<div class="rh-jogador__bloco-v">{{ $rebuys->isNotEmpty() ? Dinheiro::brlCentavos($centavos($rebuys)) : '—' }}</div>
					</div>
				@endif
				@if ($addons->isNotEmpty() || isset($botoes['addon']))
					<div class="rh-jogador__bloco">
						<span class="rh-chip rh-chip--a rh-chip--lg">A</span>
						<div class="rh-jogador__bloco-t">
							<div class="rh-label">Add-on{{ $addons->count() > 1 ? ' · '.$addons->count() : '' }}</div>
							<div class="rh-small rh-muted">{{ $addons->isNotEmpty() ? $fichas($addons->sum('fichas')) : 'Nenhum' }}</div>
						</div>
						<div class="rh-jogador__bloco-v">{{ $addons->isNotEmpty() ? Dinheiro::brlCentavos($centavos($addons)) : '—' }}</div>
					</div>
				@endif
				@if ($opcionaisComprados->isNotEmpty())
					<div class="rh-jogador__bloco">
						<span class="rh-jogador__sem-ficha" aria-hidden="true">@svg('rh-plus')</span>
						<div class="rh-jogador__bloco-t">
							<div class="rh-label">Opcionais · {{ $opcionaisComprados->count() }}</div>
							<div class="rh-small rh-muted">{{ $opcionaisComprados->pluck('item.nome')->unique()->implode(', ') }}</div>
						</div>
						<div class="rh-jogador__bloco-v">{{ Dinheiro::brlCentavos($centavos($opcionaisComprados)) }}</div>
					</div>
				@endif
			</div>

			{{-- Conta com o caixa: investido − prêmio (ou cash-out) − pago + recebido. --}}
			<div class="rh-jogador__conta">
				<dl>
					<div><dt class="rh-label">Investido</dt><dd>{{ Dinheiro::brlCentavos($saldo->investido) }}</dd></div>
					@if ($saldo->premio)
						<div><dt class="rh-label">{{ $torneio ? 'Prêmio' : 'Cash-out' }}</dt><dd>{{ Dinheiro::brlCentavos($saldo->premio) }}</dd></div>
					@endif
					<div><dt class="rh-label">Pago</dt><dd>{{ Dinheiro::brlCentavos($saldo->pago) }}</dd></div>
					<div><dt class="rh-label">Recebido</dt><dd>{{ Dinheiro::brlCentavos($saldo->recebido) }}</dd></div>
				</dl>
				<div class="rh-jogador__saldo">
					<span class="rh-label">{{ $saldo->saldo > 0 ? 'Deve ao caixa' : ($saldo->saldo < 0 ? 'Caixa deve a ele' : 'Saldo') }}</span>
					<span @class(['rh-jogador__saldo-v', 'rh-pos' => $saldo->saldo < 0])>{{ $saldo->quitado() ? 'Quitado' : Dinheiro::brlCentavos(abs($saldo->saldo)) }}</span>
				</div>
				@can('pagamentos.registrar')
					<button type="button" class="rh-btn rh-btn--block" wire:click="abrirPagamento">@svg('rh-coins') REGISTRAR PAGAMENTO</button>
				@endcan
			</div>

			{{-- Histórico: só o último registro se desfaz. --}}
			<div>
				<div class="rh-label" style="margin-bottom:8px">Histórico</div>
				<div class="rh-list rh-jogador__historico">
					@forelse ($historico as $acao)
						<div class="rh-li" wire:key="historico-{{ class_basename($acao) }}-{{ $acao->id }}">
							@if ($acao instanceof Entrada)
								@php [$texto, $cor] = $fichaDa($acao); @endphp
								@if ($texto)
									<span @class(['rh-chip', 'rh-chip--'.$cor => $cor])>{{ $texto }}</span>
								@else
									<span class="rh-jogador__sem-ficha" aria-hidden="true">@svg('rh-plus')</span>
								@endif
							@elseif ($acao instanceof Pagamento)
								<span class="rh-jogador__sem-ficha" aria-hidden="true">@svg('rh-coins')</span>
							@else
								<span class="rh-jogador__sem-ficha" aria-hidden="true">@svg('rh-skull')</span>
							@endif

							<span class="rh-jogador__h">
								{{ $this->descrever($acao) }}
								<span class="rh-faint rh-small">· {{ $acao->created_at->format('H:i') }}</span>
								@if ($acao instanceof Entrada && ($junto = $entradas->where('vinculada_a', $acao->id))->isNotEmpty())
									<span class="rh-small rh-muted">+ {{ $junto->pluck('item.nome')->implode(', ') }}</span>
								@elseif ($acao instanceof Eliminacao && $acao->voltou_com)
									<span class="rh-small rh-muted">voltou com rebuy</span>
								@endif
							</span>

							@if ($podeDesfazer && $acao->is($ultima))
								<button type="button" class="rh-btn rh-btn--ghost rh-btn--sm" wire:click="abrirDesfazer">@svg('rh-undo') Desfazer</button>
							@endif
						</div>
					@empty
						<div class="rh-li rh-muted">Nada lançado ainda</div>
					@endforelse
				</div>
				@if ($emAndamento && $quem->can('acoes.desfazer'))
					<div class="rh-field__help" style="margin-top:8px">Só o último registro pode ser desfeito, com o motivo</div>
				@endif
			</div>

			<div class="rh-jogador__fim">
				@can('mesas.organizar')
					@unless (! $mudaLugar || $participante->estaFora())
						<button type="button" class="rh-btn rh-btn--block" wire:click="$dispatch('mudar-lugar', { participanteId: {{ $participante->id }} })">@svg('rh-move') {{ $participante->jogo_mesa_id ? 'MUDAR DE LUGAR' : 'SENTAR NA MESA' }}</button>
					@endunless
				@endcan

				@can('eliminacoes.registrar')
					{{-- Sem buy-in ou último em jogo não cai (o último é o campeão). --}}
					@if ($podeSerEliminado)
						<button type="button" class="rh-btn rh-btn--danger rh-btn--block rh-btn--lg" wire:click="abrirEliminacao">@svg('rh-skull') REGISTRAR ELIMINAÇÃO</button>
					@elseif ($emAndamento && ! $torneio && $temBuyin && in_array($participante->status, [StatusParticipante::Jogando, StatusParticipante::Saiu], true))
						<button type="button" class="rh-btn rh-btn--danger rh-btn--block rh-btn--lg" wire:click="abrirCashOut">@svg('rh-coins') {{ $participante->cash_out !== null ? 'CORRIGIR CASH-OUT' : 'REGISTRAR CASH-OUT' }}</button>
					@endif
				@endcan
			</div>
		</div>
	</aside>

	@if ($modal)
		<div class="rh-scrim rh-scrim--topo">
			@if ($modal === 'entrada')
				@php
					$tipoAtual = TipoItem::from($tipo);
					$escolhido = $itens->firstWhere('id', $itemId);
					$opcoes = $tipoAtual === TipoItem::Opcional ? collect([$escolhido])->filter() : $itens->filter(fn ($i) => $i->tipo === $tipoAtual);
					$junto = $tipoAtual->eEntrada()
						? $itens->filter(fn ($i) => $i->tipo === TipoItem::Opcional
							&& ($i->compravelCom($tipoAtual) || ($i->porJogador() && ! $entradas->contains('jogo_item_id', $i->id))))
						: collect();
					$marcados = array_map('intval', $opcionais);
					$acrescimo = $junto->filter(fn ($i) => $i->obrigatorio || in_array($i->id, $marcados, true))->sum(fn ($i) => Dinheiro::centavos($i->valor));
					$novo = $saldo->investido + ($escolhido ? Dinheiro::centavos($escolhido->valor) : 0) + $acrescimo;
					$nomeDaEntrada = $tipoAtual === TipoItem::Opcional ? ($escolhido?->nome ?? 'Opcional') : $rotulo($tipoAtual);
				@endphp
				<form class="rh-modal" style="max-width:460px" wire:submit="confirmarEntrada" role="dialog" aria-modal="true" aria-labelledby="entrada-titulo">
					<div class="rh-modal__hd">
						<div>
							<div class="rh-label">{{ $tipoAtual === TipoItem::Buyin ? 'Novo buy-in' : 'Registrar '.mb_strtolower($nomeDaEntrada) }} · {{ $nome }}</div>
							<h2 class="rh-heading" id="entrada-titulo" style="font-size:24px">{{ $opcoes->count() > 1 ? 'Escolha o tipo' : $nomeDaEntrada }}</h2>
						</div>
						<button type="button" class="rh-btn rh-btn--ghost rh-btn--icon rh-x" wire:click="fecharModal" aria-label="Fechar">@svg('rh-x')</button>
					</div>
					<div class="rh-modal__bd rh-col" style="gap:12px">
						@if ($erro)
							<div class="rh-warn" role="alert">@svg('rh-alert')<div>{{ $erro }}</div></div>
						@endif

						@foreach ($opcoes as $item)
							@php
								$ficha = match ($item->tipo) {
									TipoItem::Buyin => [$jogo->fichaDoBuyin($item), null],
									TipoItem::Rebuy => ['R', 'r'],
									TipoItem::Addon => ['A', 'a'],
									default => [null, null],
								};
							@endphp
							<button type="button" class="rh-pick" wire:key="opcao-{{ $item->id }}" wire:click="escolher({{ $item->id }})" aria-pressed="{{ $item->id === $itemId ? 'true' : 'false' }}">
								@if ($ficha[0])
									<span @class(['rh-chip rh-chip--lg', 'rh-chip--'.$ficha[1] => $ficha[1]])>{{ $ficha[0] }}</span>
								@else
									<span class="rh-jogador__sem-ficha" aria-hidden="true">@svg('rh-plus')</span>
								@endif
								<span class="rh-pick__t">{{ $item->nome }}</span>
								<span class="rh-pick__v">@brl($item->valor)<small>{{ $item->fichas ? $fichas($item->fichas) : 'sem fichas' }}</small></span>
							</button>
						@endforeach

						@if ($junto->isNotEmpty())
							<div class="rh-label" style="margin-top:8px">Junto</div>
							@foreach ($junto as $opcional)
								<label class="rh-check rh-jogador__opcional" wire:key="junto-{{ $opcional->id }}">
									@if ($opcional->obrigatorio)
										<input type="checkbox" checked disabled>
									@else
										<input type="checkbox" value="{{ $opcional->id }}" wire:model.live="opcionais">
									@endif
									<span>{{ $opcional->nome }}{{ $opcional->obrigatorio ? ' (obrigatório)' : '' }}</span>
									<span class="rh-spacer"></span>
									<span class="rh-small rh-muted">{{ $opcional->fichas ? $fichas($opcional->fichas).' · ' : '' }}@brl($opcional->valor)</span>
								</label>
							@endforeach
						@endif

						<div class="rh-small rh-muted" style="margin-top:8px">Total do jogador passa de {{ Dinheiro::brlCentavos($saldo->investido) }} para <b class="rh-jogador__destaque">{{ Dinheiro::brlCentavos($novo) }}</b>.</div>
					</div>
					<div class="rh-modal__ft">
						<button type="button" class="rh-btn rh-btn--lg" wire:click="fecharModal">VOLTAR</button>
						<button type="submit" class="rh-btn rh-btn--primary rh-btn--lg" wire:loading.attr="disabled">CONFIRMAR {{ mb_strtoupper($nomeDaEntrada) }}</button>
					</div>
				</form>
			@elseif ($modal === 'pagamento')
				<form class="rh-modal" style="max-width:420px" wire:submit="confirmarPagamento" role="dialog" aria-modal="true" aria-labelledby="pagamento-titulo">
					<div class="rh-modal__hd">
						<div>
							<div class="rh-label">Pagamento · {{ $nome }}</div>
							<h2 class="rh-heading" id="pagamento-titulo" style="font-size:24px">Registrar pagamento</h2>
						</div>
						<button type="button" class="rh-btn rh-btn--ghost rh-btn--icon rh-x" wire:click="fecharModal" aria-label="Fechar">@svg('rh-x')</button>
					</div>
					<div class="rh-modal__bd rh-col" style="gap:16px">
						@if ($erro)
							<div class="rh-warn" role="alert">@svg('rh-alert')<div>{{ $erro }}</div></div>
						@endif

						<div class="rh-small rh-muted">{{ $saldo->saldo > 0 ? 'Deve ao caixa '.Dinheiro::brlCentavos($saldo->saldo) : ($saldo->saldo < 0 ? 'O caixa deve a ele '.Dinheiro::brlCentavos(-$saldo->saldo) : 'Conta quitada') }}.</div>

						<div class="rh-seg rh-jogador__seg" role="group" aria-label="Direção">
							@foreach (DirecaoPagamento::cases() as $opcao)
								<button type="button" wire:click="$set('direcao', '{{ $opcao->value }}')" aria-pressed="{{ $direcao === $opcao->value ? 'true' : 'false' }}">{{ $opcao->label() }}</button>
							@endforeach
						</div>

						<label class="rh-field">
							<span class="rh-field__label">Valor</span>
							<span class="rh-affix"><span>R$</span><input class="rh-input rh-input--money" wire:model="valor" inputmode="decimal" autocomplete="off" x-init="$nextTick(() => { $el.focus(); $el.select() })"></span>
						</label>

						<div class="rh-field">
							<span class="rh-field__label">Forma</span>
							<div class="rh-seg rh-jogador__seg" role="group" aria-label="Forma de pagamento">
								@foreach (FormaPagamento::cases() as $opcao)
									<button type="button" wire:click="$set('forma', '{{ $opcao->value }}')" aria-pressed="{{ $forma === $opcao->value ? 'true' : 'false' }}">{{ $opcao->label() }}</button>
								@endforeach
							</div>
						</div>

						<label class="rh-field">
							<span class="rh-field__label">Observação (opcional)</span>
							<input class="rh-input" wire:model="observacao" maxlength="255" autocomplete="off">
						</label>
					</div>
					<div class="rh-modal__ft">
						<button type="button" class="rh-btn rh-btn--lg" wire:click="fecharModal">VOLTAR</button>
						<button type="submit" class="rh-btn rh-btn--primary rh-btn--lg" wire:loading.attr="disabled">REGISTRAR PAGAMENTO</button>
					</div>
				</form>
			@elseif ($modal === 'eliminacao')
				@php
					$premioDaPosicao = $previsaoPosicao ? $jogo->premiacoes()->where('posicao', $previsaoPosicao)->value('valor') : null;
					$explicacao = match (true) {
						$jogo->fase === FaseTorneio::Rebuys => "Provisória: {$nome} pode voltar com rebuy até encerrar os rebuys.",
						$previsaoPosicao !== null => "Definitiva: {$nome} fica em {$previsaoPosicao}º".($premioDaPosicao ? ' e leva '.Dinheiro::brl($premioDaPosicao) : '').'.',
						default => 'Definitiva: a posição sai ao fechar as entradas',
					};
				@endphp
				<form class="rh-modal" style="max-width:400px" wire:submit="confirmarEliminacao" role="dialog" aria-modal="true" aria-labelledby="eliminacao-titulo">
					<div class="rh-modal__hd">
						<div>
							<div class="rh-label">Eliminação · {{ $nome }}</div>
							<h2 class="rh-heading" id="eliminacao-titulo" style="font-size:24px">Registrar eliminação</h2>
						</div>
						<button type="button" class="rh-btn rh-btn--ghost rh-btn--icon rh-x" wire:click="fecharModal" aria-label="Fechar">@svg('rh-x')</button>
					</div>
					<div class="rh-modal__bd rh-col" style="gap:12px">
						@if ($erro)
							<div class="rh-warn" role="alert">@svg('rh-alert')<div>{{ $erro }}</div></div>
						@endif
						<p style="margin:0">{{ $explicacao }}</p>
						@if ($jogo->nivel_atual)
							<div class="rh-small rh-muted">Nível {{ $jogo->nivel_atual }}{{ $jogo->blinds_atual ? ' · '.$jogo->blinds_atual : '' }}</div>
						@endif
					</div>
					<div class="rh-modal__ft">
						<button type="button" class="rh-btn rh-btn--lg" wire:click="fecharModal">VOLTAR</button>
						<button type="submit" class="rh-btn rh-btn--danger rh-btn--lg" wire:loading.attr="disabled">CONFIRMAR ELIMINAÇÃO</button>
					</div>
				</form>
			@elseif ($modal === 'cashout')
				@php
					$valorFicha = 'R$ '.rtrim(rtrim(number_format((float) $jogo->valor_ficha, 4, ',', '.'), '0'), ',');
					$previa = $fichasDigitadas !== null && (float) $jogo->valor_ficha > 0 ? Dinheiro::multiplicar($fichasDigitadas, $jogo->valor_ficha) : null;
				@endphp
				<form class="rh-modal" style="max-width:400px" wire:submit="confirmarCashOut" role="dialog" aria-modal="true" aria-labelledby="cashout-titulo">
					<div class="rh-modal__hd">
						<div>
							<div class="rh-label">Cash-out · {{ $nome }}</div>
							<h2 class="rh-heading" id="cashout-titulo" style="font-size:24px">Fichas finais</h2>
						</div>
						<button type="button" class="rh-btn rh-btn--ghost rh-btn--icon rh-x" wire:click="fecharModal" aria-label="Fechar">@svg('rh-x')</button>
					</div>
					<div class="rh-modal__bd rh-col" style="gap:12px">
						@if ($erro)
							<div class="rh-warn" role="alert">@svg('rh-alert')<div>{{ $erro }}</div></div>
						@endif
						<label class="rh-field">
							<span class="rh-field__label">Fichas</span>
							<input class="rh-input rh-input--money" wire:model.live.debounce.300ms="fichas" inputmode="numeric" autocomplete="off" x-init="$nextTick(() => $el.focus())">
							<span class="rh-field__help">× {{ $valorFicha }} por ficha. Sai do jogo; registrar de novo corrige.</span>
						</label>
						@if ($previa !== null)
							<div class="rh-jogador__previa">
								<span class="rh-label">Cash-out</span>
								<span class="rh-num">{{ Dinheiro::brlCentavos($previa) }}</span>
								<span class="rh-small rh-muted">Resultado <span @class(['rh-pos' => $previa > $saldo->investido, 'rh-neg' => $previa < $saldo->investido])>{{ Dinheiro::brlCentavos($previa - $saldo->investido, true) }}</span></span>
							</div>
						@endif
					</div>
					<div class="rh-modal__ft">
						<button type="button" class="rh-btn rh-btn--lg" wire:click="fecharModal">VOLTAR</button>
						<button type="submit" class="rh-btn rh-btn--danger rh-btn--lg" wire:loading.attr="disabled">CONFIRMAR CASH-OUT</button>
					</div>
				</form>
			@elseif ($modal === 'desfazer' && $ultima)
				<form class="rh-modal" style="max-width:420px" wire:submit="confirmarDesfazer" role="dialog" aria-modal="true" aria-labelledby="desfazer-titulo">
					<div class="rh-modal__hd">
						<div>
							<div class="rh-label">Desfazer · {{ $nome }}</div>
							<h2 class="rh-heading" id="desfazer-titulo" style="font-size:24px">{{ $this->descrever($ultima) }}</h2>
						</div>
						<button type="button" class="rh-btn rh-btn--ghost rh-btn--icon rh-x" wire:click="fecharModal" aria-label="Fechar">@svg('rh-x')</button>
					</div>
					<div class="rh-modal__bd rh-col" style="gap:12px">
						@if ($erro)
							<div class="rh-warn" role="alert">@svg('rh-alert')<div>{{ $erro }}</div></div>
						@endif
						<div class="rh-small rh-muted">
							Lançado às {{ $ultima->created_at->format('H:i') }}. Nada é apagado: o registro fica cancelado, com o motivo.
							@if ($ultima instanceof Entrada && $ultima->tipo === TipoItem::Rebuy && $participante->eliminacoes()->validas()->where('voltou_com', $ultima->id)->exists())
								{{ $nome }} volta a ficar eliminado.
							@elseif ($ultima instanceof Eliminacao)
								{{ $nome }} volta a jogar.
							@endif
						</div>
						<label class="rh-field">
							<span class="rh-field__label">Motivo</span>
							<input class="rh-input" wire:model="motivo" maxlength="255" autocomplete="off" placeholder="Ex.: lançado no jogador errado" x-init="$nextTick(() => $el.focus())">
						</label>
					</div>
					<div class="rh-modal__ft">
						<button type="button" class="rh-btn rh-btn--lg" wire:click="fecharModal">VOLTAR</button>
						<button type="submit" class="rh-btn rh-btn--danger rh-btn--lg" wire:loading.attr="disabled">@svg('rh-undo') DESFAZER</button>
					</div>
				</form>
			@endif
		</div>
	@endif
</div>
