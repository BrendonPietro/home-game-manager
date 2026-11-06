{{-- Tela 10: resumo ao vivo (docs/prototipo, tela 10), com saldos e pendências. Atualiza a cada 5 s. --}}
@php
	use App\Enums\StatusParticipante;
	use App\Support\Dinheiro;

	$torneio = $jogo->eTorneio();
	$taxa = $jogo->taxas->count() === 1 ? $jogo->taxas->first() : null;
	$fichas = fn (int $n): string => number_format($n, 0, ',', '.');
	$numeros = array_filter([
		'Jogando' => $contagem['jogando'],
		'Buy-ins' => $contagem['jogadores'],
		$torneio ? 'Rebuys' : 'Recompras' => $contagem['rebuys'],
		'Add-ons' => $torneio ? $contagem['addons'] : null,
		'Total de entradas' => $resumo->entradas,
	], fn ($valor) => $valor !== null);
@endphp

<div class="rh-col rh-resumo" style="gap:16px" wire:poll.5s>
	<div class="rh-row">
		<div>
			<div class="rh-label">Durante {{ $torneio ? 'o torneio' : 'o jogo' }}</div>
			<h1 class="rh-title">Resumo ao vivo</h1>
		</div>
		<span class="rh-spacer"></span>
		<span class="rh-small rh-muted">Atualizado às {{ now()->format('H:i:s') }}</span>
	</div>

	<div class="rh-resumo__numeros">
		@foreach ($numeros as $rotulo => $valor)
			<div class="rh-stat"><span class="rh-label">{{ $rotulo }}</span><span class="rh-stat__v">{{ $valor }}</span></div>
		@endforeach
		<div class="rh-stat"><span class="rh-label">Arrecadação</span><span class="rh-stat__v">{{ Dinheiro::brlCentavos($resumo->arrecadacao) }}</span></div>
		<div class="rh-stat"><span class="rh-label">{{ $taxa ? $taxa->nome.' '.$taxa->resumo() : 'Taxas' }}</span><span class="rh-stat__v rh-muted">{{ Dinheiro::brlCentavos($resumo->totalTaxas) }}</span></div>
		<div class="rh-stat rh-resumo__prize"><span class="rh-label">Prize pool</span><span class="rh-stat__v">{{ Dinheiro::brlCentavos($resumo->prizePool) }}</span></div>
	</div>

	{{-- Pendências com o caixa: o que falta receber, o que falta pagar e o que já entrou. --}}
	<div class="rh-resumo__caixa">
		<div class="rh-stat"><span class="rh-label">Jogadores devem ao caixa</span><span class="rh-stat__v">{{ Dinheiro::brlCentavos($pendencias['devem']) }}</span></div>
		<div class="rh-stat"><span class="rh-label">Caixa deve aos jogadores</span><span @class(['rh-stat__v', 'rh-pos' => $pendencias['aReceber'] > 0])>{{ Dinheiro::brlCentavos($pendencias['aReceber']) }}</span></div>
		<div class="rh-stat"><span class="rh-label">Entrou no caixa</span><span class="rh-stat__v">{{ Dinheiro::brlCentavos($pendencias['noCaixa']) }}</span><span class="rh-stat__s">pago − recebido</span></div>
	</div>

	<section class="rh-panel">
		<div class="rh-panel__hd rh-resumo__hd">
			<h3 class="rh-heading">Jogadores</h3>
			<span class="rh-spacer"></span>
			<label class="rh-check"><input type="checkbox" wire:model.live="soPendentes"> Só pendentes ({{ $pendencias['quantos'] }})</label>
			@if ($mesas->count() > 1)
				<div class="rh-seg" role="group" aria-label="Mesa">
					<button type="button" wire:click="filtrarMesa(null)" aria-pressed="{{ $mesa === null ? 'true' : 'false' }}">Todas as mesas</button>
					@foreach ($mesas as $jogoMesa)
						<button type="button" wire:key="filtro-mesa-{{ $jogoMesa->id }}" wire:click="filtrarMesa({{ $jogoMesa->id }})" aria-pressed="{{ $mesa === $jogoMesa->id ? 'true' : 'false' }}">{{ $jogoMesa->mesa->nome }}</button>
					@endforeach
				</div>
			@endif
		</div>

		<div class="rh-resumo__tabela">
			<table class="rh-table">
				<thead>
					<tr>
						<th>Jogador</th>
						<th>Mesa</th>
						<th>Buy-in</th>
						<th class="n">{{ $torneio ? 'Rebuys' : 'Recompras' }}</th>
						@if ($torneio)
							<th class="n">Add-on</th>
						@endif
						<th class="n">Fichas</th>
						<th class="n">Total</th>
						@if ($mostraPremio)
							<th class="n">{{ $torneio ? 'Prêmio' : 'Cash-out' }}</th>
						@endif
						<th class="n">Pago</th>
						<th class="n">Saldo</th>
					</tr>
				</thead>
				<tbody>
					@forelse ($linhas as ['participante' => $participante, 'entradas' => $compras, 'conta' => $conta])
						@php
							$situacao = match ($participante->status) {
								StatusParticipante::Eliminado => $participante->posicao_final ? $participante->posicao_final.'º' : 'fora',
								StatusParticipante::Saiu => 'saiu',
								default => null,
							};
							$semMovimento = $conta->investido === 0 && $conta->pago === 0 && $conta->recebido === 0 && $conta->premio === 0;
						@endphp
						<tr wire:key="linha-{{ $participante->id }}" @class(['rh-resumo__linha', 'is-selected' => $selecionado === $participante->id]) wire:click="selecionar({{ $participante->id }})">
							<td>
								<span class="rh-who">
									<x-site.props.avatar :nome="$participante->nome()" :foto="$participante->usuario->foto" tamanho="sm" />
									{{ $participante->nome() }}
									@if ($situacao)
										<span class="rh-badge rh-badge--off">{{ $situacao }}</span>
									@endif
								</span>
							</td>
							<td class="rh-muted">{{ $participante->jogoMesa ? $participante->jogoMesa->mesa->nome.' · '.$participante->lugar : 'Sem lugar' }}</td>
							<td>
								@if ($compras?->buyin)
									<span class="rh-chip">{{ $jogo->fichaDoBuyin($compras->buyin) }}</span>
									<span class="rh-muted rh-small">{{ $compras->buyin->nome }}</span>
								@else
									<span class="rh-faint rh-small">sem buy-in</span>
								@endif
							</td>
							<td class="n">{!! $compras?->rebuys ? e($compras->rebuys) : '<span class="rh-faint">—</span>' !!}</td>
							@if ($torneio)
								<td class="n">{!! $compras?->addons ? e($compras->addons) : '<span class="rh-faint">—</span>' !!}</td>
							@endif
							<td class="n rh-muted">{{ $compras ? $fichas($compras->fichas) : '—' }}</td>
							<td class="n">{{ Dinheiro::brlCentavos($conta->investido) }}</td>
							@if ($mostraPremio)
								<td class="n">{{ $conta->premio ? Dinheiro::brlCentavos($conta->premio) : '—' }}</td>
							@endif
							<td class="n">
								{{ Dinheiro::brlCentavos($conta->pago) }}
								@if ($conta->recebido)
									<span class="rh-resumo__recebeu">recebeu {{ Dinheiro::brlCentavos($conta->recebido) }}</span>
								@endif
							</td>
							<td @class(['n', 'rh-neg' => $conta->saldo > 0, 'rh-pos' => $conta->saldo < 0, 'rh-faint' => $conta->quitado()])>
								{{ $semMovimento ? '—' : ($conta->saldo > 0 ? 'deve '.Dinheiro::brlCentavos($conta->saldo) : ($conta->saldo < 0 ? 'recebe '.Dinheiro::brlCentavos(-$conta->saldo) : 'quitado')) }}
							</td>
						</tr>
					@empty
						<tr><td colspan="11" class="rh-muted">{{ $soPendentes ? 'Ninguém com pendência' : 'Nenhum jogador nesta mesa' }}</td></tr>
					@endforelse
				</tbody>
				<tfoot>
					<tr>
						<td>{{ $linhas->count() === $total ? $total.' jogadores' : $linhas->count().' de '.$total.' jogadores' }}</td>
						<td></td>
						<td></td>
						<td class="n">{{ $linhas->sum(fn ($l) => $l['entradas']?->rebuys ?? 0) }}</td>
						@if ($torneio)
							<td class="n">{{ $linhas->sum(fn ($l) => $l['entradas']?->addons ?? 0) }}</td>
						@endif
						<td class="n">{{ $fichas($linhas->sum(fn ($l) => $l['entradas']?->fichas ?? 0)) }}</td>
						<td class="n">{{ Dinheiro::brlCentavos($linhas->sum(fn ($l) => $l['conta']->investido)) }}</td>
						@if ($mostraPremio)
							<td class="n">{{ Dinheiro::brlCentavos($linhas->sum(fn ($l) => $l['conta']->premio)) }}</td>
						@endif
						<td class="n">{{ Dinheiro::brlCentavos($linhas->sum(fn ($l) => $l['conta']->pago)) }}</td>
						<td></td>
					</tr>
				</tfoot>
			</table>
		</div>
	</section>

	{{-- Clicar num jogador abre o painel dele (o mesmo da tela ao vivo, sem mudar de lugar). --}}
	@if ($selecionado)
		<livewire:jogo.acoes-jogador :jogo="$jogo" :participante-id="$selecionado" :muda-lugar="false" :key="'acoes-'.$selecionado" />
	@endif
</div>
