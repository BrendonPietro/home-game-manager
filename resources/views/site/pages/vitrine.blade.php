{{-- Vitrine do design system (só em ambiente local): confere o SCSS do site contra docs/prototipo. --}}
@php
	$cores = [
		'bg-000', 'bg-100', 'bg-200', 'bg-300', 'bg-400', 'line-100', 'line-200', 'line-control',
		'ink-100', 'ink-200', 'ink-300', 'red-600', 'red-500', 'red-400', 'red-300', 'on-red',
		'green-300', 'amber-300', 'blue-300', 'focus', 'felt-900', 'felt-700', 'felt-500', 'felt-300',
		'rail-700', 'rail-500', 'chip-buyin', 'chip-rebuy', 'chip-addon', 'chip-dealer',
	];

	$icones = collect(File::files(resource_path('svg/site/icons')))
		->map(fn ($arquivo) => $arquivo->getFilenameWithoutExtension())
		->sort()
		->values();

	// Mesa 01 da Poker Night #18. Posição provisória em elipse simples; a real (por comprimento
	// de arco, com rotação) vem do mesa.js na fase 3.
	$assentos = [
		1 => ['João', 'JO', 150, 'B', null, 'A'],
		2 => ['Lucas', 'LU', 200, 'B', 'R', null],
		3 => ['Carlos', 'CA', 250, 'B', 'R', 'A'],
		4 => null,
		5 => ['Rafael', 'RA', 200, '2', null, null],
		6 => ['André', 'AN', 200, 'B', 'R', null],
		7 => ['Bruno', 'BR', 100, 'B', null, null],
		8 => ['Pedro', 'PE', 200, 'B', 'R', null],
		9 => null,
	];
	$posicao = function (int $slot) {
		$angulo = deg2rad(90 + $slot * 36);

		return sprintf('left:%.2f%%;top:%.2f%%', 50 + 42 * cos($angulo), 50 + 38 * sin($angulo));
	};
@endphp

<x-site.app titulo="Vitrine">
	<header class="rh-top">
		<x-site.props.brand />
		<div class="rh-top__ctx">
			<b>Poker Night #18</b>
			<span class="rh-badge rh-badge--live">Ao vivo</span>
			<span class="rh-small rh-muted">04/10/2026 · 20:00</span>
		</div>
		<span class="rh-spacer"></span>
		<a class="rh-btn rh-btn--ghost rh-btn--sm" href="{{ url('/') }}">@svg('rh-list') Torneios</a>
		<span class="rh-avatar rh-avatar--sm">BR</span>
	</header>

	<nav class="rh-flow">
		<div class="rh-flow__grp">
			<span class="rh-label">Antes</span>
			<a class="rh-step is-done" href="#"><i>✓</i>Conferência</a>
			<a class="rh-step is-done" href="#"><i>✓</i>Jogadores</a>
			<a class="rh-step is-done" href="#"><i>✓</i>Mesas</a>
		</div>
		<div class="rh-flow__grp">
			<span class="rh-label">Durante</span>
			<a class="rh-step" href="#" aria-current="step"><i>4</i>Ao vivo</a>
			<a class="rh-step" href="#"><i>5</i>Resumo</a>
		</div>
		<div class="rh-flow__grp">
			<span class="rh-label">Final</span>
			<a class="rh-step" href="#"><i>6</i>Premiação</a>
			<a class="rh-step" href="#"><i>7</i>Resultado</a>
			<a class="rh-step" href="#"><i>8</i>Fechamento</a>
		</div>
	</nav>

	<div class="rh-strip">
		<div><span class="rh-label">Nível 6</span><span class="v">100 / 200</span></div>
		<div><span class="rh-label">Relógio</span><span class="v">14:32</span></div>
		<div><span class="rh-label">Jogadores</span><span class="v">12</span></div>
		<div><span class="rh-label">Entradas</span><span class="v">22</span></div>
		<div><span class="rh-label">Arrecadação</span><span class="v">R$ 2.100</span></div>
		<div class="is-prize"><span class="rh-label">Prize pool</span><span class="v">R$ 1.890</span></div>
	</div>

	<main class="rh-vitrine">
		<div>
			<h1 class="rh-title">Vitrine do design system</h1>
			<p class="rh-muted" style="margin:4px 0 0">
				Componentes do <code>resources/scss/site</code> com os dados da Poker Night #18. Compare com <code>docs/prototipo/</code>.
			</p>
		</div>

		<section class="rh-vitrine__sec">
			<span class="rh-label">Cores</span>
			<div class="rh-vitrine__cores">
				@foreach ($cores as $cor)
					<div class="rh-vitrine__cor"><i style="background:var(--{{ $cor }})"></i>{{ $cor }}</div>
				@endforeach
			</div>
		</section>

		<section class="rh-vitrine__sec">
			<span class="rh-label">Tipografia e dinheiro</span>
			<div class="rh-grid rh-grid--2">
				<div class="rh-col">
					<h2 class="rh-title">Poker Night #18</h2>
					<h3 class="rh-heading">Mesa 01 · 7 jogadores</h3>
					<span class="rh-label">Rótulo de seção</span>
					<p style="margin:0">Texto em Barlow. <span class="rh-muted">Secundário.</span> <span class="rh-faint">Terciário.</span></p>
					<span class="rh-small rh-muted">Texto pequeno: 13 px</span>
					<span>Link: <a class="rh-link" href="#">Esqueci minha senha</a></span>
				</div>
				<div class="rh-col">
					<span class="rh-stat__v rh-stat__v--xl">R$ 1.890</span>
					<span class="rh-num" style="font-size:22px;font-weight:600"><span class="rh-pos">+R$ 795</span> · <span class="rh-neg">−R$ 200</span></span>
					<span>Naipes: <span class="rh-suit">♠ ♥ ♣ ♦</span></span>
				</div>
			</div>
		</section>

		<section class="rh-vitrine__sec">
			<span class="rh-label">Marca e botões</span>
			<div class="rh-row" style="gap:24px">
				<x-site.props.brand />
				<x-site.props.brand sub="Painel admin" />
			</div>
			<div class="rh-row">
				<button class="rh-btn rh-btn--primary">@svg('rh-plus') Novo jogador</button>
				<button class="rh-btn">Secundário</button>
				<button class="rh-btn rh-btn--ghost">Ghost</button>
				<button class="rh-btn rh-btn--danger">@svg('rh-undo') Desfazer</button>
				<button class="rh-btn rh-btn--sm">Pequeno</button>
				<button class="rh-btn rh-btn--icon" aria-label="Mais">@svg('rh-more')</button>
				<button class="rh-btn" disabled>Desabilitado</button>
			</div>
			<div class="rh-row">
				<button class="rh-btn rh-btn--primary rh-btn--lg">Iniciar jogo</button>
				<button class="rh-btn rh-btn--lg">Encerrar rebuys</button>
			</div>
			<div class="rh-grid rh-grid--3">
				<button class="rh-action">
					<span class="rh-action__top"><span class="rh-chip rh-chip--lg">B</span>Buy-in</span>
					<span class="rh-action__meta">R$ 100 · 10.000 fichas</span>
				</button>
				<button class="rh-action">
					<span class="rh-action__top"><span class="rh-chip rh-chip--r rh-chip--lg">R</span>Rebuy</span>
					<span class="rh-action__meta">R$ 100 · 10.000 fichas</span>
				</button>
				<button class="rh-action" disabled>
					<span class="rh-action__top"><span class="rh-chip rh-chip--a rh-chip--lg">A</span>Add-on</span>
					<span class="rh-action__meta">Abre no intervalo</span>
				</button>
			</div>
		</section>

		<section class="rh-vitrine__sec">
			<span class="rh-label">Status, fichas e avatares</span>
			<div class="rh-row">
				<span class="rh-badge rh-badge--live">Ao vivo</span>
				<span class="rh-badge rh-badge--next">Próximo</span>
				<span class="rh-badge rh-badge--ok">Pago</span>
				<span class="rh-badge rh-badge--warn">Pendente</span>
				<span class="rh-badge rh-badge--done">Encerrado</span>
				<span class="rh-badge rh-badge--off">Cancelado</span>
				<span class="rh-badge rh-badge--plain">@svg('rh-lock') Restrito</span>
			</div>
			<div class="rh-row" style="gap:14px">
				<span class="rh-chip">B</span>
				<span class="rh-chip rh-chip--r">R</span>
				<span class="rh-chip rh-chip--a">A</span>
				<span class="rh-chip rh-chip--lg">2</span>
				<span class="rh-chipcount"><span class="rh-chip rh-chip--r">R</span>×2</span>
				<span class="rh-chipcount rh-chipcount--zero"><span class="rh-chip rh-chip--a">A</span>×0</span>
				<span class="rh-dealer-btn">D</span>
				<span class="rh-avatar rh-avatar--sm">JO</span>
				<span class="rh-avatar">CA</span>
				<span class="rh-avatar rh-avatar--lg">BR</span>
			</div>
		</section>

		<section class="rh-vitrine__sec">
			<span class="rh-label">Formulário</span>
			<div class="rh-grid rh-grid--3">
				<label class="rh-field">
					<span class="rh-field__label">Nome</span>
					<input class="rh-input" value="Marcão">
					<span class="rh-field__help">Apelido e telefone são opcionais.</span>
				</label>
				<label class="rh-field">
					<span class="rh-field__label">Mesa</span>
					<select class="rh-select"><option>Mesa 01</option><option>Mesa 02</option></select>
				</label>
				<label class="rh-field">
					<span class="rh-field__label">Senha</span>
					<input class="rh-input" type="password" value="errada" aria-invalid="true">
					<span class="rh-field__error">Usuário ou senha incorretos.</span>
				</label>
				<label class="rh-field">
					<span class="rh-field__label">Buy-in</span>
					<span class="rh-affix"><span>R$</span><input class="rh-input rh-input--money" value="100"></span>
				</label>
				<label class="rh-field">
					<span class="rh-field__label">Rake</span>
					<span class="rh-affix"><input class="rh-input rh-input--money" value="10"><span class="r">%</span></span>
				</label>
				<div class="rh-field">
					<span class="rh-field__label">Buscar jogador</span>
					<div class="rh-search">@svg('rh-search')<input class="rh-input" placeholder="Nome ou apelido"></div>
				</div>
			</div>
			<div class="rh-row" style="gap:24px">
				<label class="rh-check"><input type="checkbox" checked> Lembrar acesso</label>
				<label class="rh-check"><input type="checkbox"> Pode ser dealer</label>
				<label class="rh-row"><input class="rh-switch" type="checkbox" checked> Vale para o ranking</label>
			</div>
			<textarea class="rh-input" placeholder="Observações da noite"></textarea>
		</section>

		<section class="rh-vitrine__sec">
			<span class="rh-label">Abas e filtro</span>
			<div class="rh-tabs" role="tablist">
				<button class="rh-tab" role="tab" aria-selected="true">Jogando <span class="rh-count">10</span></button>
				<button class="rh-tab" role="tab" aria-selected="false">Eliminados <span class="rh-count">2</span></button>
				<button class="rh-tab" role="tab" aria-selected="false">Pendências</button>
			</div>
			<div class="rh-seg">
				<button aria-pressed="true">Todos</button>
				<button aria-pressed="false">Mesa 01</button>
				<button aria-pressed="false">Mesa 02</button>
			</div>
		</section>

		<section class="rh-vitrine__sec">
			<span class="rh-label">Números da noite</span>
			<div class="rh-grid rh-grid--4">
				<div class="rh-stat"><span class="rh-label">Arrecadação</span><span class="rh-stat__v">R$ 2.100</span><span class="rh-stat__s">22 entradas</span></div>
				<div class="rh-stat"><span class="rh-label">Rake 10%</span><span class="rh-stat__v">R$ 210</span><span class="rh-stat__s">Taxas da casa</span></div>
				<div class="rh-stat rh-stat--prize"><span class="rh-label">Prize pool</span><span class="rh-stat__v">R$ 1.890</span><span class="rh-stat__s">3 premiados</span></div>
				<div class="rh-stat"><span class="rh-label">Jogadores</span><span class="rh-stat__v">12</span><span class="rh-stat__s">2 mesas</span></div>
			</div>
		</section>

		<section class="rh-vitrine__sec">
			<span class="rh-label">Painéis, tabela e lista</span>
			<div class="rh-grid rh-grid--side">
				<div class="rh-panel">
					<div class="rh-panel__hd"><h3 class="rh-heading">Saldos</h3><span class="rh-spacer"></span><span class="rh-badge rh-badge--warn">2 pendentes</span></div>
					<table class="rh-table">
						<thead><tr><th>Jogador</th><th>Entradas</th><th class="n">Investido</th><th class="n">Saldo</th></tr></thead>
						<tbody>
							<tr><td><span class="rh-who"><span class="rh-avatar rh-avatar--sm">MA</span>Marcão</span></td><td><span class="rh-row"><span class="rh-chip">B</span><span class="rh-chip rh-chip--r">R</span><span class="rh-chip rh-chip--a">A</span></span></td><td class="n">R$ 250</td><td class="n rh-pos">+R$ 695</td></tr>
							<tr class="is-selected"><td><span class="rh-who"><span class="rh-avatar rh-avatar--sm">BR</span>Brendon</span></td><td><span class="rh-row"><span class="rh-chip">B</span><span class="rh-chip rh-chip--r">R</span><span class="rh-chip rh-chip--a">A</span></span></td><td class="n">R$ 250</td><td class="n rh-pos">+R$ 317</td></tr>
							<tr><td><span class="rh-who"><span class="rh-avatar rh-avatar--sm">TH</span>Thiago</span></td><td><span class="rh-chip">B</span></td><td class="n">R$ 100</td><td class="n rh-neg">−R$ 100</td></tr>
						</tbody>
						<tfoot><tr><td>Total</td><td></td><td class="n">R$ 2.100</td><td class="n">R$ 0</td></tr></tfoot>
					</table>
					<div class="rh-panel__ft"><button class="rh-btn rh-btn--sm">@svg('rh-down') Exportar</button></div>
				</div>

				<div class="rh-col" style="gap:16px">
					<div class="rh-panel">
						<div class="rh-panel__hd"><h3 class="rh-heading">Premiação</h3></div>
						<div class="rh-panel__bd">
							<dl class="rh-kv">
								<dt>1º lugar · 50%</dt><dd>R$ 945</dd>
								<dt>2º lugar · 30%</dt><dd>R$ 567</dd>
								<dt>3º lugar · 20%</dt><dd>R$ 378</dd>
							</dl>
							<hr class="rh-divider">
							<dl class="rh-kv"><dt>Prize pool</dt><dd>R$ 1.890</dd></dl>
						</div>
					</div>

					<div class="rh-panel">
						<div class="rh-list">
							<div class="rh-li">@svg('rh-users')<div><div class="rh-li__t">12 confirmados</div><div class="rh-li__s">2 aguardando lugar</div></div></div>
							<div class="rh-li">@svg('rh-table')<div><div class="rh-li__t">2 mesas</div><div class="rh-li__s">Dealers: Sérgio e Paulo</div></div></div>
						</div>
					</div>
				</div>
			</div>
		</section>

		<section class="rh-vitrine__sec">
			<span class="rh-label">Próximo jogo</span>
			<div class="rh-feature">
				<div>
					<span class="rh-badge rh-badge--next">Próximo</span>
					<h2 class="rh-title" style="font-size:40px;line-height:42px;margin-top:10px">Poker Night #19</h2>
					<div class="rh-feature__facts">
						<div><span class="rh-label">Buy-in</span><div class="v">R$ 100</div></div>
						<div><span class="rh-label">Confirmados</span><div class="v">14</div></div>
						<div><span class="rh-label">Garantido</span><div class="v">R$ 1.500</div></div>
					</div>
				</div>
				<div class="rh-col" style="align-items:flex-end;justify-content:space-between">
					<x-site.props.datebox :data="\Illuminate\Support\Carbon::parse('2026-10-11')" />
					<a class="rh-btn rh-btn--primary rh-btn--lg" href="#">Abrir jogo @svg('rh-arrow-r')</a>
				</div>
			</div>
		</section>

		<section class="rh-vitrine__sec">
			<span class="rh-label">Escolha, aviso e modal</span>
			<div class="rh-grid rh-grid--2">
				<div class="rh-col" style="gap:10px">
					<button class="rh-pick" aria-pressed="true"><span class="rh-chip rh-chip--lg">B</span><span class="rh-pick__t">Buy-in simples</span><span class="rh-pick__v">R$ 100<small>10.000 fichas</small></span></button>
					<button class="rh-pick" aria-pressed="false"><span class="rh-chip rh-chip--lg">2</span><span class="rh-pick__t">Buy-in duplo</span><span class="rh-pick__v">R$ 200<small>20.000 fichas</small></span></button>
					<div class="rh-warn">@svg('rh-alert')<div><b>Antes de fechar as entradas</b><ul><li>2 jogadores com pagamento pendente</li><li>Mesa 02 sem dealer</li></ul></div></div>
				</div>
				<div class="rh-modal">
					<div class="rh-modal__hd"><h3 class="rh-heading">Fechar entradas?</h3><button class="rh-btn rh-btn--ghost rh-btn--icon rh-x" aria-label="Fechar">@svg('rh-x')</button></div>
					<div class="rh-modal__bd"><p class="rh-muted" style="margin:0">Os prêmios são gravados e as próximas eliminações já dão a posição.</p></div>
					<div class="rh-modal__ft"><button class="rh-btn">Voltar</button><button class="rh-btn rh-btn--primary">Fechar entradas</button></div>
				</div>
			</div>
		</section>

		<section class="rh-vitrine__sec">
			<span class="rh-label">Jogadores</span>
			<div class="rh-grid rh-grid--4">
				<button class="rh-pcard is-on"><span class="rh-avatar rh-avatar--sm">JO</span><span class="rh-pcard__n">João</span><span class="rh-tick"></span></button>
				<button class="rh-pcard is-on"><span class="rh-avatar rh-avatar--sm">LU</span><span class="rh-pcard__n">Lucas</span><span class="rh-tick"></span></button>
				<button class="rh-pcard"><span class="rh-avatar rh-avatar--sm">FE</span><span class="rh-pcard__n">Felipe</span><span class="rh-tick"></span></button>
				<button class="rh-pcard"><span class="rh-avatar rh-avatar--sm">DI</span><span class="rh-pcard__n">Diego</span><span class="rh-tick"></span></button>
			</div>
		</section>

		<section class="rh-vitrine__sec">
			<span class="rh-label">Mesa</span>
			<div class="rh-vitrine__mesa">
				<div class="rh-ptable" style="--ar:1.7;--inset:15% 9%">
					<div class="rh-ptable__rail">
						<div class="rh-ptable__felt">
							<div class="rh-ptable__center">
								<span class="rh-ptable__name">Mesa 01</span>
								<span class="rh-label">7 jogadores · dealer Sérgio</span>
								<span class="rh-ptable__suits">♠♥♣♦</span>
							</div>
						</div>
					</div>

					<div class="rh-seat rh-seat--dealer" style="{{ $posicao(0) }}">
						<span class="rh-label">Dealer</span><span class="rh-seat__name">Sérgio</span>
						<span class="rh-dealer-btn">D</span>
					</div>

					@foreach ($assentos as $lugar => $jogador)
						@if ($jogador)
							@php
								[$nome, $iniciais, $total, $b, $r, $a] = $jogador;
							@endphp
							<button @class(['rh-seat', 'is-active' => $nome === 'Carlos', 'rh-seat--out' => $nome === 'Bruno']) style="{{ $posicao($lugar) }}">
								<span class="rh-seat__hd">
									<span class="rh-seat__no">{{ str_pad($lugar, 2, '0', STR_PAD_LEFT) }}</span>
									<span class="rh-avatar rh-avatar--sm">{{ $iniciais }}</span>
									<span class="rh-seat__name">{{ $nome }}</span>
								</span>
								<span class="rh-seat__ft">
									<span class="rh-seat__total">R$ {{ $total }}</span>
									<span class="rh-seat__chips">
										<span class="rh-chip">{{ $b }}</span>
										@if ($r)<span class="rh-chip rh-chip--r">{{ $r }}</span>@endif
										@if ($a)<span class="rh-chip rh-chip--a">{{ $a }}</span>@endif
									</span>
								</span>
							</button>
						@else
							<button class="rh-seat rh-seat--empty" style="{{ $posicao($lugar) }}"><span class="rh-seat__name">{{ $lugar }} · vago</span></button>
						@endif
					@endforeach
				</div>
			</div>
		</section>

		<section class="rh-vitrine__sec">
			<span class="rh-label">Acertos</span>
			<div class="rh-panel">
				<div class="rh-xfer"><span class="rh-who"><span class="rh-avatar rh-avatar--sm">TH</span>Thiago</span><span class="rh-xfer__arrow">@svg('rh-arrow-r')</span><span class="rh-who"><span class="rh-avatar rh-avatar--sm">MA</span>Marcão</span><span class="rh-xfer__v">R$ 100</span></div>
				<div class="rh-xfer"><span class="rh-who"><span class="rh-avatar rh-avatar--sm">DI</span>Diego</span><span class="rh-xfer__arrow">@svg('rh-arrow-r')</span><span class="rh-who"><span class="rh-avatar rh-avatar--sm">BR</span>Brendon</span><span class="rh-xfer__v">R$ 100</span></div>
			</div>
		</section>

		<section class="rh-vitrine__sec">
			<span class="rh-label">Ícones ({{ $icones->count() }}) · @@svg('rh-nome')</span>
			<div class="rh-vitrine__cores">
				@foreach ($icones as $icone)
					<div class="rh-row rh-small rh-muted">@svg('rh-'.$icone) {{ $icone }}</div>
				@endforeach
			</div>
		</section>
	</main>
</x-site.app>
