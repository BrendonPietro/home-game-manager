{{-- Tela 02: dashboard do site (docs/prototipo, tela 02). --}}
@php
	use App\Enums\StatusJogo;
	use App\Enums\TipoEvento;
	use App\Support\Datas;
	use App\Support\Dinheiro;
	use App\Support\EtapaDoJogo;
@endphp

<x-site.app titulo="Início">
	<x-site.window.topbar />

	<main class="rh-page">
		<x-site.props.aviso class="rh-wrap" />

		<div class="rh-wrap rh-grid rh-grid--side" style="--side:340px;gap:20px;align-items:start">
			<div class="rh-col" style="gap:20px">
				@if ($proximo)
					@php
						$aoVivo = $proximo->status === StatusJogo::EmAndamento;
						$buyin = $proximo->buyinPadrao();
						$abrir = EtapaDoJogo::url($proximo);
					@endphp

					<section class="rh-feature">
						<div>
							<div class="rh-row">
								<span class="rh-label" style="color:var(--ink-100)">{{ $aoVivo ? 'Acontecendo agora' : 'Próximo '.($proximo->eTorneio() ? 'torneio' : 'cash game') }}</span>
								<x-site.props.status-jogo :jogo="$proximo" />
							</div>

							<div class="rh-row" style="gap:16px;margin-top:14px;flex-wrap:nowrap">
								<x-site.props.datebox :data="$proximo->data" />
								<div style="min-width:0">
									<h2 class="rh-title" style="font-size:40px;line-height:42px">{{ $proximo->nome }}</h2>
									<div class="rh-muted" style="margin-top:4px">
										{{ Datas::porExtenso($proximo->data) }}@if ($proximo->horario) · {{ Datas::hora($proximo->horario) }}@endif
										@if ($proximo->temporada) · Temporada {{ $proximo->temporada->nome }}@endif
									</div>
								</div>
							</div>

							<div class="rh-feature__facts">
								<div><div class="rh-label">Confirmados</div><div class="v">{{ $proximo->participantes_count }} {{ $proximo->participantes_count === 1 ? 'jogador' : 'jogadores' }}</div></div>
								<div><div class="rh-label">Mesas</div><div class="v">{{ $proximo->mesas_count }}</div></div>
								@if ($buyin)
									<div><div class="rh-label">Buy-in principal</div><div class="v">@brl($buyin->valor)</div></div>
								@endif
								<div>
									<div class="rh-label">{{ $aoVivo ? 'Prize pool' : 'Prize pool estimado' }}</div>
									<div class="v">{{ Dinheiro::brlCentavos($resumoDoProximo->prizePool) }}</div>
								</div>
							</div>
						</div>

						<div class="rh-col" style="justify-content:flex-end;align-items:flex-end">
							@if ($abrir)
								<a class="rh-btn rh-btn--primary rh-btn--lg" href="{{ $abrir }}">@svg('rh-arrow-r') Abrir {{ $proximo->eTorneio() ? 'torneio' : 'jogo' }}</a>
							@else
								<button class="rh-btn rh-btn--primary rh-btn--lg" disabled title="A tela desta etapa ainda não existe">@svg('rh-arrow-r') Abrir {{ $proximo->eTorneio() ? 'torneio' : 'jogo' }}</button>
							@endif
							<span class="rh-small rh-muted">
								@if ($aoVivo)
									{{ $proximo->nivel_atual ? 'Nível '.$proximo->nivel_atual.($proximo->blinds_atual ? ' · '.$proximo->blinds_atual : '') : 'Em andamento' }}
								@else
									Começa {{ Str::lcfirst(Datas::quando($proximo->data)) }}
								@endif
							</span>
						</div>
					</section>
				@else
					<section class="rh-panel">
						<div class="rh-panel__bd rh-col" style="gap:6px;padding:24px">
							<h2 class="rh-heading">Nenhum jogo agendado</h2>
							<p class="rh-muted" style="margin:0">Quando um jogo for agendado no painel, ele aparece aqui.</p>
						</div>
					</section>
				@endif

				<div class="rh-grid rh-grid--4">
					<div class="rh-stat">
						<span class="rh-label">Em andamento</span>
						<span class="rh-stat__v">{{ $numeros['emAndamento'] }}</span>
						<span class="rh-stat__s">{{ $numeros['emAndamento'] ? 'ao vivo agora' : 'nenhum jogo ao vivo' }}</span>
					</div>
					<div class="rh-stat">
						<span class="rh-label">Próximos</span>
						<span class="rh-stat__v">{{ $numeros['proximos'] }}</span>
						<span class="rh-stat__s">{{ $numeros['ateData'] ? 'até '.$numeros['ateData']->format('d/m') : 'nada agendado' }}</span>
					</div>
					<div class="rh-stat">
						<span class="rh-label">Jogadores ativos</span>
						<span class="rh-stat__v">{{ $numeros['jogadores'] }}</span>
						<span class="rh-stat__s">cadastrados</span>
					</div>
					<div class="rh-stat">
						<span class="rh-label">Média por noite</span>
						<span class="rh-stat__v">{{ $numeros['media'] !== null ? number_format($numeros['media'], 1, ',', '.') : '—' }}</span>
						<span class="rh-stat__s">{{ $numeros['mediaDe'] ? 'últimos '.$numeros['mediaDe'].' '.($numeros['mediaDe'] === 1 ? 'evento' : 'eventos') : 'nenhuma noite finalizada' }}</span>
					</div>
				</div>

				<section class="rh-panel">
					<div class="rh-panel__hd">
						<h3 class="rh-heading">Últimos eventos</h3>
						<span class="rh-spacer"></span>
						<a class="rh-btn rh-btn--ghost rh-btn--sm" href="{{ route('jogos.index', ['aba' => 'finalizados']) }}">Ver todos @svg('rh-chev-r')</a>
					</div>

					@if ($ultimos->isEmpty())
						<div class="rh-panel__bd rh-muted">Nenhuma noite finalizada ainda.</div>
					@else
						<table class="rh-table">
							<thead>
								<tr><th>Jogo</th><th>Data</th><th class="n">Jogadores</th><th class="n">Mesas</th><th class="n">Arrecadação</th><th>Campeão</th><th>Status</th></tr>
							</thead>
							<tbody>
								@foreach ($ultimos as $jogo)
									<tr>
										<td><b>{{ $jogo->nome }}</b></td>
										<td class="rh-muted">{{ $jogo->data->format('d/m/Y') }}</td>
										<td class="n">{{ $jogo->participantes_count }}</td>
										<td class="n">{{ $jogo->mesas_count }}</td>
										<td class="n">@brl($jogo->arrecadacao)</td>
										<td>
											@if ($campeao = $jogo->campeao?->usuario)
												<span class="rh-who"><x-site.props.avatar :nome="$campeao->nome_exibicao" :foto="$campeao->foto" tamanho="sm" />{{ $campeao->nome_exibicao }}</span>
											@else
												<span class="rh-faint">—</span>
											@endif
										</td>
										<td><x-site.props.status-jogo :jogo="$jogo" /></td>
									</tr>
								@endforeach
							</tbody>
						</table>
					@endif
				</section>
			</div>

			<aside class="rh-col" style="gap:20px">
				<section class="rh-panel">
					<div class="rh-panel__hd"><h3 class="rh-heading">Agenda</h3></div>

					@if ($agenda->isEmpty())
						<div class="rh-panel__bd rh-muted">Nada na agenda.</div>
					@else
						<div class="rh-list">
							@foreach ($agenda as $evento)
								@php
									[$classe, $texto] = match (true) {
										$evento->jogo?->status === StatusJogo::EmAndamento => ['rh-badge--live', 'Ao vivo'],
										$evento->jogo && $evento->jogo_id === $proximo?->id => ['rh-badge--next', 'Próximo'],
										$evento->tipo === TipoEvento::Jogo => ['rh-badge--done', 'Agendado'],
										$evento->tipo === TipoEvento::Aluguel => ['rh-badge--warn', 'Aluguel'],
										$evento->tipo === TipoEvento::Bloqueio => ['rh-badge--off', 'Bloqueio'],
										default => ['rh-badge--plain', $evento->tipo->label()],
									};
									$mesas = $evento->mesas->count();
								@endphp
								<div class="rh-li">
									<x-site.props.datebox :data="$evento->data" pequena />
									<div style="flex:1;min-width:0">
										<div class="rh-li__t">{{ $evento->nome }}</div>
										<div class="rh-li__s">
											{{ Datas::hora($evento->hora_inicio) ?? 'Dia todo' }}@if ($mesas) · {{ $mesas }} {{ $mesas === 1 ? 'mesa' : 'mesas' }}@endif
										</div>
									</div>
									<span class="rh-badge {{ $classe }}">{{ $texto }}</span>
								</div>
							@endforeach
						</div>
					@endif
				</section>

				@if ($ultimaNoite)
					<section class="rh-panel">
						<div class="rh-panel__hd">
							<h3 class="rh-heading">Última noite</h3>
							<span class="rh-spacer"></span>
							<span class="rh-small rh-muted">{{ $ultimaNoite->nome }}</span>
						</div>
						<div class="rh-panel__bd">
							<dl class="rh-kv">
								<dt>Entradas</dt><dd>{{ $resumoDaUltima->entradas }}</dd>
								<dt>Arrecadação</dt><dd>{{ Dinheiro::brlCentavos($resumoDaUltima->arrecadacao) }}</dd>
								<dt>Taxas</dt><dd>{{ Dinheiro::brlCentavos($resumoDaUltima->totalTaxas) }}</dd>
								<dt>Prize pool</dt><dd>{{ Dinheiro::brlCentavos($resumoDaUltima->prizePool) }}</dd>
							</dl>
						</div>
					</section>
				@endif
			</aside>
		</div>
	</main>
</x-site.app>
