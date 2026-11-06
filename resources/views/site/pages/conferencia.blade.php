{{-- Tela 04: conferência da estrutura (docs/prototipo, tela 04). Tudo vem do cadastro no painel. --}}
@php
	use App\Enums\DestinoItem;
	use App\Enums\MetodoPremiacao;
	use App\Enums\TipoItem;
	use App\Models\EstruturaBlinds;
	use App\Support\Datas;
	use App\Support\Dinheiro;
	use App\Support\EtapaDoJogo;

	$torneio = $jogo->eTorneio();
	$nomeDoJogo = $torneio ? 'torneio' : 'jogo';
	$doTipo = fn (TipoItem $tipo) => $jogo->itens->where('tipo', $tipo);
	$padrao = $jogo->buyinPadrao();
	$limite = fn ($item): string => $item->limite_por_jogador
		? $item->limite_por_jogador.' por jogador'
		: 'sem limite';

	$percentuais = collect($estimado->premios)->pluck('percentual')->map(fn ($p) => rtrim(rtrim($p, '0'), '.'));
	$editarNoPainel = route('filament.painel.resources.jogos.edit', $jogo);
	$seguir = EtapaDoJogo::url($jogo, 'jogadores');
@endphp

<x-site.operacao :jogo="$jogo" etapa="conferencia" titulo="Conferência">
	<div class="rh-row" style="gap:16px">
		<div>
			<div class="rh-label">Antes do {{ $nomeDoJogo }} · 1 de 3</div>
			<h1 class="rh-title">Conferência da estrutura</h1>
			<div class="rh-muted">Tudo abaixo foi definido no painel. Confira com o grupo antes de começar.</div>
		</div>
		<span class="rh-spacer"></span>
		<a class="rh-btn" href="{{ $editarNoPainel }}">@svg('rh-edit') Editar no painel</a>
		@if ($seguir)
			<a class="rh-btn rh-btn--primary rh-btn--lg" href="{{ $seguir }}">@svg('rh-check') Conferido, seguir</a>
		@else
			<button class="rh-btn rh-btn--primary rh-btn--lg" disabled title="A próxima tela ainda não existe">@svg('rh-check') Conferido, seguir</button>
		@endif
	</div>

	<div class="rh-grid rh-conferencia">
		<div class="rh-col" style="gap:16px">
			<section class="rh-panel">
				<div class="rh-panel__hd">
					<h3 class="rh-heading">Buy-ins disponíveis</h3>
					<span class="rh-spacer"></span>
					<span class="rh-small rh-muted">fichas · valor</span>
				</div>
				@forelse ($doTipo(TipoItem::Buyin)->where('ativo', true) as $item)
					<x-site.props.linha-item :ficha="$jogo->fichaDoBuyin($item)" :titulo="$item->nome" :fichas="$item->fichas" :valor="$item->valor">
						{{ $item->is($padrao) ? 'Entrada padrão' : 'Conta como 1 entrada' }} · {{ $limite($item) }}
					</x-site.props.linha-item>
				@empty
					<div class="rh-panel__bd rh-neg">Nenhum buy-in ativo: ajuste no painel antes de começar.</div>
				@endforelse
			</section>

			<section class="rh-panel">
				<div class="rh-panel__hd"><h3 class="rh-heading">{{ $torneio ? 'Rebuy e add-on' : 'Recompra' }}</h3></div>
				@foreach ($doTipo(TipoItem::Rebuy) as $item)
					<x-site.props.linha-item ficha="R" cor="r" :titulo="$item->nome" :fichas="$item->fichas" :valor="$item->valor" :desativado="! $item->ativo">
						@if ($item->ativo)
							<span class="rh-pos" style="font-weight:600">Disponível</span> · {{ $limite($item) }}{{ $torneio ? ', até encerrar rebuys' : '' }}
						@else
							Não tem {{ $torneio ? 'rebuy' : 'recompra' }} neste jogo
						@endif
					</x-site.props.linha-item>
				@endforeach
				@if ($torneio)
					@foreach ($doTipo(TipoItem::Addon) as $item)
						<x-site.props.linha-item ficha="A" cor="a" :titulo="$item->nome" :fichas="$item->fichas" :valor="$item->valor" :desativado="! $item->ativo">
							@if ($item->ativo)
								<span class="rh-pos" style="font-weight:600">Disponível</span> · {{ $limite($item) }}, no intervalo
							@else
								Não tem add-on neste torneio
							@endif
						</x-site.props.linha-item>
					@endforeach
				@endif
				@if ($doTipo(TipoItem::Rebuy)->isEmpty() && ($doTipo(TipoItem::Addon)->isEmpty() || ! $torneio))
					<div class="rh-panel__bd rh-muted">Sem {{ $torneio ? 'rebuy nem add-on' : 'recompra' }}.</div>
				@endif
			</section>

			@if ($doTipo(TipoItem::Opcional)->where('ativo', true)->isNotEmpty())
				<section class="rh-panel">
					<div class="rh-panel__hd"><h3 class="rh-heading">Opcionais</h3></div>
					@foreach ($doTipo(TipoItem::Opcional)->where('ativo', true) as $item)
						<x-site.props.linha-item :titulo="$item->nome" :fichas="$item->fichas" :valor="$item->valor">
							{{ $item->porJogador() ? '1 por jogador' : 'Junto de cada entrada' }}
							· {{ match ($item->destino) { DestinoItem::Premiacao => 'vai para a premiação', DestinoItem::Metade => '50/50 na premiação', DestinoItem::Fora => 'não vai para a premiação' } }}
							@if ($item->obrigatorio) · <b>obrigatório</b>@endif
						</x-site.props.linha-item>
					@endforeach
				</section>
			@endif

			@if ($torneio && $jogo->estruturaBlinds)
				<section class="rh-panel">
					<div class="rh-panel__hd">
						<h3 class="rh-heading">Blinds · {{ $jogo->estruturaBlinds->nome }}</h3>
						<span class="rh-spacer"></span>
						<span class="rh-small rh-muted">{{ $jogo->estruturaBlinds->resumo() }}</span>
					</div>
					<div class="rh-conferencia__niveis">
						<table class="rh-table">
							<thead><tr><th>Nível</th><th>Blinds</th><th class="n">Ante</th><th class="n">Duração</th><th class="n">Começa</th></tr></thead>
							<tbody>
								@foreach (EstruturaBlinds::linhas($jogo->estruturaBlinds->niveis) as $linha)
									<tr @class(['is-intervalo' => $linha['intervalo']])>
										@if ($linha['intervalo'])
											<td colspan="3">{{ $linha['blinds'] }}</td>
										@else
											<td>{{ $linha['numero'] }}</td>
											<td>{{ $linha['blinds'] }}</td>
											<td class="n">{{ $linha['ante'] ?? '—' }}</td>
										@endif
										<td class="n">{{ $linha['minutos'] }} min</td>
										<td class="n">{{ $linha['comeca'] }}</td>
									</tr>
								@endforeach
							</tbody>
						</table>
					</div>
				</section>
			@endif

			@if ($torneio)
				{{-- v3: o link do relógio, para deixar a TV e os tablets prontos antes de começar. --}}
				<section class="rh-panel" x-data="{ copiado: false }">
					<div class="rh-panel__hd"><h3 class="rh-heading">Relógio nas outras telas</h3></div>
					<div class="rh-panel__bd rh-col" style="gap:10px">
						<span class="rh-small rh-muted">Abra na TV, no tablet ou em outro celular, sem login: mostra só o relógio e começa a contar quando o torneio for iniciado.</span>
						<div class="rh-link-relogio">{{ $jogo->linkDoRelogio() }}</div>
						<div class="rh-row" style="gap:8px;flex-wrap:wrap">
							<button type="button" class="rh-btn rh-btn--primary" x-on:click="$copiar(@js($jogo->linkDoRelogio())).then(() => { copiado = true; setTimeout(() => copiado = false, 2500) })">@svg('rh-copy') <span x-text="copiado ? 'COPIADO' : 'COPIAR LINK'">COPIAR LINK</span></button>
							<a class="rh-btn" href="{{ $jogo->linkDoRelogio() }}" target="_blank" rel="noopener">@svg('rh-tv') ABRIR</a>
						</div>
					</div>
				</section>
			@endif
		</div>

		<div class="rh-col" style="gap:16px">
			<section class="rh-panel">
				<div class="rh-panel__hd"><h3 class="rh-heading">Evento</h3></div>
				<div class="rh-panel__bd">
					<dl class="rh-kv">
						<dt>Nome</dt><dd>{{ $jogo->nome }}</dd>
						<dt>Modalidade</dt><dd>{{ $jogo->modalidade->label() }}</dd>
						<dt>Data</dt><dd>{{ Datas::porExtenso($jogo->data) }}</dd>
						<dt>Horário</dt><dd>{{ Datas::hora($jogo->horario) ?? '—' }}</dd>
						@if ($jogo->temporada)
							<dt>Temporada</dt><dd>{{ $jogo->temporada->nome }}</dd>
						@endif
						@if ($torneio)
							<dt>Estrutura de blinds</dt><dd>{{ $jogo->estruturaBlinds?->nome ?? 'Digitada na noite' }}</dd>
						@else
							<dt>Valor da ficha</dt><dd>R$ {{ str_replace('.', ',', rtrim(rtrim((string) $jogo->valor_ficha, '0'), '.')) }}</dd>
						@endif
					</dl>
				</div>
			</section>

			<section class="rh-panel">
				<div class="rh-panel__hd"><h3 class="rh-heading">Financeiro</h3></div>
				<div class="rh-panel__bd">
					<dl class="rh-kv">
						@forelse ($jogo->taxas as $taxa)
							<dt>{{ $taxa->nome }}</dt><dd>{{ $taxa->resumo() }}</dd>
						@empty
							<dt>Taxas</dt><dd>Nenhuma</dd>
						@endforelse

						@if ($torneio)
							<dt>Premiação</dt>
							<dd>
								@if ($jogo->metodo_premiacao === MetodoPremiacao::Manual)
									Manual · {{ $percentuais->implode('/') ?: '—' }}
								@elseif ($percentuais->isNotEmpty())
									{{ $percentuais->count() }} {{ $percentuais->count() === 1 ? 'posição' : 'posições' }} · {{ $percentuais->implode('/') }}
								@else
									Pela regra do clube
								@endif
							</dd>
						@else
							<dt>Resultado</dt><dd>Cash-out: fichas × valor</dd>
						@endif
					</dl>

					<hr class="rh-divider">

					<dl class="rh-kv">
						<dt>Prize pool estimado</dt><dd>{{ Dinheiro::brlCentavos($estimado->prizePool) }}</dd>
					</dl>
					<div class="rh-small rh-faint" style="margin-top:6px">
						Com {{ $jogo->participantes_count }} {{ $jogo->participantes_count === 1 ? 'confirmado' : 'confirmados' }} e o buy-in padrão, sem rebuys{{ $torneio && $jogo->metodo_premiacao !== MetodoPremiacao::Manual ? '; a divisão segue a regra pelo número de jogadores' : '' }}.
					</div>
				</div>
			</section>

			<section class="rh-panel">
				<div class="rh-panel__hd"><h3 class="rh-heading">Mesas</h3></div>
				<div class="rh-panel__bd">
					@if ($jogo->mesas->isEmpty())
						<p class="rh-muted" style="margin:0">Nenhuma mesa ainda. Elas se montam na etapa Mesas.</p>
					@else
						<dl class="rh-kv">
							<dt>Quantidade de mesas</dt><dd>{{ $jogo->mesas->count() }}</dd>
							<dt>Lugares</dt><dd>{{ $jogo->mesas->map(fn ($m) => $m->mesa->nome.': '.$m->lugares)->implode(' · ') }}</dd>
							<dt>Capacidade total</dt><dd>{{ $jogo->mesas->sum('lugares') }} lugares</dd>
							<dt>Dealers</dt><dd>{{ $jogo->mesas->map(fn ($m) => $m->dealer?->nome_exibicao)->filter()->implode(' · ') ?: 'Sem dealer' }}</dd>
						</dl>
					@endif
					@if ($jogo->floor)
						<dl class="rh-kv" style="margin-top:8px"><dt>Floor</dt><dd>{{ $jogo->floor->nome_exibicao }}</dd></dl>
					@endif
				</div>
			</section>
		</div>
	</div>
</x-site.operacao>
