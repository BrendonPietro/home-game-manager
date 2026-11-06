{{-- Tela 03: escolher o jogo que vai operar (docs/prototipo, tela 03). --}}
@php
	use App\Enums\StatusJogo;
	use App\Support\Datas;
	use App\Support\EtapaDoJogo;

	$abas = ['proximos' => 'Próximos', 'andamento' => 'Em andamento', 'finalizados' => 'Finalizados'];
@endphp

<x-site.app titulo="Jogos">
	<x-site.window.topbar />

	<main class="rh-page">
		<div class="rh-wrap rh-col" style="max-width:1180px;gap:16px">
			<x-site.props.aviso />

			<div class="rh-row" style="gap:16px">
				<div>
					<h1 class="rh-title">Escolha o jogo</h1>
					<div class="rh-muted">Selecione qual evento você vai operar agora</div>
				</div>
				<span class="rh-spacer"></span>
				<form method="GET" action="{{ route('jogos.index') }}" class="rh-search rh-jogos__busca" role="search">
					@svg('rh-search')
					<input type="hidden" name="aba" value="{{ $aba }}">
					<input class="rh-input" type="search" name="busca" value="{{ $busca }}" placeholder="Buscar jogo" aria-label="Buscar jogo">
				</form>
			</div>

			@if ($destaque)
				@php
					$aoVivo = $destaque->status === StatusJogo::EmAndamento;
					$buyin = $destaque->buyinPadrao();
					$abrir = EtapaDoJogo::url($destaque);
				@endphp
				<div class="rh-label">{{ $aoVivo ? 'Ao vivo agora' : 'Jogo de hoje' }}</div>
				<div class="rh-jogo-destaque">
					<x-site.props.datebox :data="$destaque->data" />
					<div style="min-width:0">
						<div class="rh-row">
							<b class="rh-heading" style="font-size:24px">{{ $destaque->nome }}</b>
							<span @class(['rh-badge', 'rh-badge--live' => $aoVivo, 'rh-badge--next' => ! $aoVivo])>
								{{ $aoVivo ? 'Ao vivo' : 'Hoje' }}@if ($destaque->horario) · {{ Datas::hora($destaque->horario) }}@endif
							</span>
						</div>
						<div class="rh-small rh-muted" style="margin-top:4px">
							{{ $destaque->modalidade->label() }}@if ($destaque->temporada) · Temporada {{ $destaque->temporada->nome }}@endif
						</div>
					</div>
					<div><div class="rh-label">Confirmados</div><div class="v">{{ $destaque->participantes_count }}</div></div>
					<div><div class="rh-label">Mesas</div><div class="v">{{ $destaque->mesas_count }}</div></div>
					<div><div class="rh-label">Buy-in</div><div class="v">{{ $buyin ? \App\Support\Dinheiro::brl($buyin->valor) : '—' }}</div></div>
					<div><div class="rh-label">Taxas</div><div class="v">{{ $destaque->taxas->map->resumo()->implode(' + ') ?: '—' }}</div></div>
					@if ($abrir)
						<a class="rh-btn rh-btn--primary rh-btn--lg" href="{{ $abrir }}">Abrir</a>
					@else
						<button class="rh-btn rh-btn--primary rh-btn--lg" disabled title="A tela desta etapa ainda não existe">Abrir</button>
					@endif
				</div>
			@endif

			<div>
				<nav class="rh-tabs" aria-label="Jogos por status">
					@foreach ($abas as $chave => $rotulo)
						<a class="rh-tab" href="{{ route('jogos.index', ['aba' => $chave]) }}" aria-selected="{{ $aba === $chave ? 'true' : 'false' }}">
							{{ $rotulo }} <span class="rh-count">{{ $contagem[$chave] }}</span>
						</a>
					@endforeach
				</nav>

				<section class="rh-panel" style="border-top-left-radius:0;border-top-right-radius:0;border-top:0">
					@if ($jogos->isEmpty())
						<div class="rh-panel__bd rh-muted">
							{{ $busca !== '' ? 'Nenhum jogo com "'.$busca.'".' : 'Nenhum jogo aqui' }}
						</div>
					@else
						<table class="rh-table">
							<thead>
								<tr><th>Jogo</th><th>Data</th><th>Horário</th><th>Status</th><th class="n">Confirmados</th><th class="n">Mesas</th><th class="n">Buy-in</th><th></th></tr>
							</thead>
							<tbody>
								@foreach ($jogos as $jogo)
									@php
										$abrir = EtapaDoJogo::url($jogo);
										$buyin = $jogo->buyinPadrao();
										$emDestaque = $jogo->is($destaque);
									@endphp
									<tr @class(['is-selected' => $emDestaque])>
										<td><b>{{ $jogo->nome }}</b>@if ($jogo->eCashGame()) <span class="rh-small rh-faint">cash game</span>@endif</td>
										<td>{{ Datas::curta($jogo->data) }}</td>
										<td class="rh-muted">{{ Datas::hora($jogo->horario) ?? '—' }}</td>
										<td><x-site.props.status-jogo :jogo="$jogo" /></td>
										<td class="n">{{ $jogo->participantes_count }}</td>
										<td class="n">{{ $jogo->mesas_count }}</td>
										<td class="n">{{ $buyin ? \App\Support\Dinheiro::brl($buyin->valor) : '—' }}</td>
										<td style="text-align:right">
											@if ($abrir)
												<a @class(['rh-btn rh-btn--sm', 'rh-btn--primary' => $emDestaque]) href="{{ $abrir }}">Abrir @svg('rh-chev-r')</a>
											@else
												<button class="rh-btn rh-btn--sm" disabled title="A tela desta etapa ainda não existe">Abrir @svg('rh-chev-r')</button>
											@endif
										</td>
									</tr>
								@endforeach
							</tbody>
						</table>
					@endif
				</section>
			</div>

			{{ $jogos->links('site.partials.paginacao') }}
		</div>
	</main>
</x-site.app>
