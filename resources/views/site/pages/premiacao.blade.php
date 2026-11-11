{{-- Tela 12: premiação (docs/prototipo, tela 12). Prêmios gravados ao fechar as entradas. --}}
@php
	use App\Enums\MetodoPremiacao;
	use App\Support\Dinheiro;
	use App\Support\EtapaDoJogo;

	$premios = $jogo->premiacoes;
	$taxas = $jogo->taxas;
	// 50 / '50.00' → '50%', '7.50' → '7,5%' (só para mostrar).
	$numero = fn ($percentual): string => str_replace('.', ',', (string) (float) $percentual);
	$pct = fn ($percentual): string => $numero($percentual).'%';
	$maior = (float) ($premios->max(fn ($p) => (float) $p->percentual) ?: 1);
	$distribuido = $premios->sum(fn ($p) => Dinheiro::centavos($p->valor));
	$somaPercentuais = $premios->sum(fn ($p) => Dinheiro::centavos($p->percentual));
	// Gravado com "Arredondar prêmios para R$ 5": algum prêmio do 2º para baixo não é a conta exata.
	$arredondado = $premios->contains(fn ($p) => $p->posicao > 1
		&& Dinheiro::centavos($p->valor) !== intdiv($resumo->prizePool * Dinheiro::centavos($p->percentual), 10000));
	$retirado = $resumo->totalTaxas + $resumo->foraDaPremiacao;
	$urlResultado = EtapaDoJogo::permite($jogo, 'resultado') ? EtapaDoJogo::url($jogo, 'resultado') : null;
	$faixa = fn ($regra): string => match (true) {
		$regra->jogadores_max === null => $regra->jogadores_min.' ou mais',
		$regra->jogadores_min <= 1 => 'até '.$regra->jogadores_max,
		default => $regra->jogadores_min.' a '.$regra->jogadores_max,
	};
	$daNoite = fn ($regra): bool => $resumo->jogadores >= $regra->jogadores_min
		&& ($regra->jogadores_max === null || $resumo->jogadores <= $regra->jogadores_max);
@endphp

<x-site.operacao :jogo="$jogo" etapa="premiacao" titulo="Premiação">
	<div class="rh-row">
		<div>
			<div class="rh-label">Final · {{ $jogo->estaFinalizado() ? 'finalizado às '.$jogo->finalizado_em->format('H:i') : 'entradas fechadas às '.$jogo->entradas_fechadas_em?->format('H:i') }}</div>
			<h1 class="rh-title">Premiação</h1>
		</div>
		<span class="rh-spacer"></span>
		@if ($regras->isNotEmpty())
			<a class="rh-btn" href="#regra">@svg('rh-ver') Ver regra de distribuição</a>
		@endif
		@if ($urlResultado)
			<a class="rh-btn rh-btn--primary rh-btn--lg" href="{{ $urlResultado }}">{{ $jogo->estaFinalizado() ? 'VER RESULTADO' : 'REGISTRAR RESULTADO' }} @svg('rh-chevron-direita')</a>
		@endif
	</div>

	<div class="rh-grid rh-premiacao">
		<section class="rh-panel">
			<div class="rh-panel__hd"><h3 class="rh-heading">Do arrecadado ao prêmio</h3></div>
			<div class="rh-panel__bd">
				<dl class="rh-kv">
					<dt>Total arrecadado</dt><dd>{{ Dinheiro::brlCentavos($resumo->arrecadacao) }}</dd>
					@if ($resumo->foraDaPremiacao)
						<dt>Fora da premiação (opcionais)</dt><dd class="rh-muted">−{{ Dinheiro::brlCentavos($resumo->foraDaPremiacao) }}</dd>
					@endif
					@forelse ($resumo->taxas as $indice => $taxa)
						<dt>{{ $taxa['nome'] }}{{ isset($taxas[$indice]) ? ' ('.$taxas[$indice]->resumo().')' : '' }}</dt><dd class="rh-muted">−{{ Dinheiro::brlCentavos($taxa['valor']) }}</dd>
					@empty
						<dt>Taxas</dt><dd class="rh-muted">R$ 0</dd>
					@endforelse
				</dl>
				<hr class="rh-divider">
				<dl class="rh-kv">
					<dt>Valor retirado antes da premiação</dt><dd>{{ Dinheiro::brlCentavos($retirado) }}</dd>
				</dl>
				<div class="rh-premiacao__pool">
					<div class="rh-label">Prize pool · valor final distribuído</div>
					<div class="rh-premiacao__pool-v">{{ Dinheiro::brlCentavos($resumo->prizePool) }}</div>
				</div>
			</div>
		</section>

		<div class="rh-col" style="gap:16px">
			<section class="rh-panel">
				<div class="rh-panel__hd">
					<h3 class="rh-heading">Distribuição</h3>
					<span class="rh-badge rh-badge--plain">{{ $premios->count() }} {{ $premios->count() === 1 ? 'posição premiada' : 'posições premiadas' }}</span>
					<span class="rh-spacer"></span>
					<span class="rh-small rh-muted">{{ $jogo->metodo_premiacao === MetodoPremiacao::Manual ? 'Manual' : 'Regra' }}: {{ $premios->map(fn ($p) => $numero($p->percentual))->join(' / ') }}</span>
				</div>

				@forelse ($premios as $premio)
					@php $quem = $naPosicao->get($premio->posicao); @endphp
					<div class="rh-premiacao__posicao">
						<span @class(['rh-premiacao__lugar', 'is-primeiro' => $premio->posicao === 1])>{{ $premio->posicao }}º</span>
						<div class="rh-premiacao__meio">
							<div class="rh-label">{{ $premio->posicao }}º lugar · {{ $pct($premio->percentual) }}</div>
							<div class="rh-premiacao__barra"><span style="width:{{ round((float) $premio->percentual / $maior * 100, 1) }}%"></span></div>
							<div class="rh-small {{ $quem ? '' : 'rh-faint' }}">{{ $quem ? $quem->nome() : 'em jogo' }}</div>
						</div>
						<span class="rh-premiacao__valor">@brl($premio->valor)</span>
					</div>
				@empty
					<div class="rh-panel__bd rh-muted">Nenhum prêmio gravado</div>
				@endforelse

				<div class="rh-premiacao__soma">
					<span class="rh-muted">Soma distribuída</span>
					<span class="rh-spacer"></span>
					<span class="rh-num">{{ Dinheiro::brlCentavos($distribuido) }} · {{ $pct(Dinheiro::decimal($somaPercentuais)) }}</span>
				</div>
				@if ($arredondado)
					<div class="rh-premiacao__soma rh-small rh-muted">Prêmios arredondados para R$ 5: a diferença foi para o 1º</div>
				@endif
			</section>

			@if ($regras->isNotEmpty())
				<section class="rh-panel" id="regra">
					<div class="rh-panel__hd">
						<h3 class="rh-heading">Regra de distribuição</h3>
						<span class="rh-spacer"></span>
						<span class="rh-small rh-muted">Padrão do clube · definida no painel</span>
					</div>
					<div class="rh-premiacao__tabela">
					<table class="rh-table">
						<thead><tr><th>Jogadores na noite</th><th class="n">Premiados</th><th>Percentuais</th></tr></thead>
						<tbody>
							@foreach ($regras as $regra)
								<tr @class(['is-selected' => $daNoite($regra)])>
									<td>
										@if ($daNoite($regra))
											<b>{{ $faixa($regra) }}</b> <span class="rh-badge rh-badge--ok rh-badge--plain">hoje: {{ $resumo->jogadores }}</span>
										@else
											{{ $faixa($regra) }}
										@endif
									</td>
									<td class="n">{{ count($regra->percentuais) }}</td>
									<td>{{ collect($regra->percentuais)->map(fn ($p) => $pct($p))->join(' · ') }}</td>
								</tr>
							@endforeach
						</tbody>
					</table>
					</div>
				</section>
			@endif
		</div>
	</div>
</x-site.operacao>
