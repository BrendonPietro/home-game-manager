{{--
	Modal Jogadores (Livewire Jogo\Sorteio, telas 06 e 07): marcar quem chegou, pôr na lista alguém do
	cadastro ou um jogador novo e sortear o lugar deles nas mesas marcadas. Abre pelo evento abrir-sorteio.
--}}
<div>
	@if ($aberto)
		<div class="rh-scrim" wire:keydown.escape.window="fechar">
			<div class="rh-modal rh-sorteio" role="dialog" aria-modal="true" aria-labelledby="sorteio-titulo">
				<div class="rh-modal__hd">
					<div>
						<div class="rh-label">{{ $resultado ? 'Lugares sorteados' : 'Quem chegou e onde senta' }}</div>
						<h2 class="rh-heading" id="sorteio-titulo" style="font-size:24px">Jogadores</h2>
					</div>
					<span class="rh-spacer"></span>
					<button type="button" class="rh-btn rh-btn--ghost rh-btn--icon rh-x" wire:click="fechar" aria-label="Fechar">@svg('rh-fechar')</button>
				</div>

				@if ($resultado)
					<div class="rh-modal__bd rh-col rh-sorteio__corpo" style="gap:8px">
						@foreach ($resultado as $sorteado)
							<div class="rh-sorteio__lugar" wire:key="sorteado-{{ $loop->index }}">
								<x-site.props.avatar :nome="$sorteado['nome']" tamanho="sm" />
								<div class="rh-sorteio__quem">
									<span class="rh-pcard__n">{{ $sorteado['nome'] }}</span>
									@if ($sorteado['entregar'])
										<span class="rh-small rh-muted">{{ ucfirst($sorteado['entregar']) }}</span>
									@endif
								</div>
								<span class="rh-sorteio__onde">{{ $sorteado['onde'] }}</span>
							</div>
						@endforeach
					</div>

					<div class="rh-modal__ft rh-sorteio__ft">
						<button type="button" class="rh-btn" wire:click="sortearMais">@svg('rh-girar') Sortear mais</button>
						<button type="button" class="rh-btn rh-btn--primary" wire:click="fechar">Pronto</button>
					</div>
				@else
					<div class="rh-modal__bd rh-col rh-sorteio__corpo" style="gap:16px">
						@if ($erro)
							<div class="rh-warn" role="alert">@svg('rh-alerta')<div>{{ $erro }}</div></div>
						@elseif ($aviso)
							<div class="rh-warn rh-warn--ok" role="status">@svg('rh-check')<div>{{ $aviso }}</div></div>
						@endif

						<div class="rh-col rh-sorteio__mesas-bloco" style="gap:8px">
							<span class="rh-label">Em quais mesas</span>
							<div class="rh-sorteio__mesas">
								@foreach ($mesasDoJogo as $jogoMesa)
									@php($naMesa = in_array($jogoMesa->id, $mesas, true))
									<button type="button" @class(['rh-pcard', 'rh-sorteio__mesa', 'is-on' => $naMesa]) wire:key="mesa-{{ $jogoMesa->id }}" wire:click="alternarMesa({{ $jogoMesa->id }})" aria-pressed="{{ $naMesa ? 'true' : 'false' }}">
										<span class="rh-tick" aria-hidden="true"></span>
										<span class="rh-sorteio__mesa-txt">
											<span class="rh-pcard__n">{{ $jogoMesa->nome }}</span>
											<span class="rh-small rh-muted">{{ $livres[$jogoMesa->id] }} {{ $livres[$jogoMesa->id] === 1 ? 'lugar livre' : 'lugares livres' }}</span>
										</span>
									</button>
								@endforeach
							</div>
							@if ($entraComBuyin)
								<span class="rh-small rh-muted">{{ $entraComBuyin }}</span>
							@endif
						</div>

						<div class="rh-search">
							@svg('rh-pesquisar')
							<input class="rh-input" type="search" wire:model.live.debounce.250ms="busca" placeholder="Buscar pelo nome ou apelido" aria-label="Buscar jogador" x-init="$nextTick(() => $el.focus())">
						</div>

						<div class="rh-tabs" role="tablist" aria-label="Jogadores">
							<button type="button" class="rh-tab" role="tab" aria-selected="{{ $aba === 'sem-lugar' ? 'true' : 'false' }}" wire:click="verAba('sem-lugar')">Sem lugar <span class="rh-count">{{ $semLugar }}</span></button>
							<button type="button" class="rh-tab" role="tab" aria-selected="{{ $aba === 'sentados' ? 'true' : 'false' }}" wire:click="verAba('sentados')">Na mesa <span class="rh-count">{{ $sentados }}</span></button>
						</div>

						<div class="rh-col" style="gap:8px">
							<div class="rh-row" style="gap:8px;align-items:center">
								<span class="rh-small rh-muted">{{ $aba === 'sentados' ? 'Marque quem vai trocar de lugar' : 'Marque quem chegou' }}</span>
								<span class="rh-spacer"></span>
								@if ($lista->isNotEmpty())
									<button type="button" class="rh-btn rh-btn--ghost rh-btn--sm" wire:click="marcarTodos">Marcar todos</button>
								@endif
								@if ($quantos > 0)
									<button type="button" class="rh-btn rh-btn--ghost rh-btn--sm" wire:click="limpar">Limpar</button>
								@endif
							</div>

							@forelse ($lista as $participante)
								@php($marcado = in_array($participante->id, $escolhidos, true))
								<button type="button" @class(['rh-pcard', 'is-on' => $marcado]) wire:key="lista-{{ $participante->id }}" wire:click="alternar({{ $participante->id }})" aria-pressed="{{ $marcado ? 'true' : 'false' }}">
									<span class="rh-tick" aria-hidden="true"></span>
									<x-site.props.avatar :nome="$participante->nome()" :foto="$participante->usuario->foto" tamanho="sm" />
									<span class="rh-pcard__n">{{ $participante->usuario->nome_com_apelido }}</span>
									@if ($bonus && $participante->pagou_antes)
										<span class="rh-badge rh-badge--ok">Pagou antes · +{{ number_format($bonus, 0, ',', '.') }}</span>
									@endif
									<x-site.props.pagos-antes :participante="$participante" />
									@if ($participante->jogoMesa)
										<span class="rh-small rh-muted">{{ $participante->jogoMesa->nome }} · Lugar {{ $participante->lugar }}</span>
									@endif
								</button>
							@empty
								<span class="rh-small rh-muted">
									@if ($busca !== '')
										Ninguém {{ $aba === 'sentados' ? 'na mesa' : 'sem lugar' }} com "{{ $busca }}"
									@else
										{{ $aba === 'sentados' ? 'Ninguém sentado ainda' : 'Todo mundo da lista já tem lugar' }}
									@endif
								</span>
							@endforelse
						</div>

						@if ($cadastro->isNotEmpty())
							@can('jogadores.confirmar')
								<div class="rh-col" style="gap:8px">
									<span class="rh-label">No cadastro, fora da lista</span>
									@foreach ($cadastro as $usuario)
										<button type="button" class="rh-pcard" wire:key="cadastro-{{ $usuario->id }}" wire:click="adicionarDoCadastro({{ $usuario->id }})" wire:loading.attr="disabled">
											@svg('rh-adicionar', 'rh-faint')
											<x-site.props.avatar :nome="$usuario->nome_exibicao" :foto="$usuario->foto" tamanho="sm" />
											<span class="rh-pcard__n">{{ $usuario->nome_com_apelido }}</span>
											<span class="rh-small rh-faint">pôr na lista</span>
										</button>
									@endforeach
								</div>
							@endcan
						@endif

						@can('usuarios.cadastro_rapido')
							<form class="rh-col rh-sorteio__novo" style="gap:8px" wire:submit="cadastrar">
								<span class="rh-label">Jogador novo</span>
								<div class="rh-row" style="gap:8px">
									<input class="rh-input" style="flex:2;min-width:140px" wire:model="novoNome" placeholder="Nome" aria-label="Nome do jogador novo" autocomplete="off">
									<input class="rh-input" style="flex:1;min-width:100px" wire:model="novoApelido" placeholder="Apelido (opcional)" aria-label="Apelido do jogador novo" autocomplete="off">
								</div>
								<button type="submit" class="rh-btn" wire:loading.attr="disabled">@svg('rh-adicionar') Cadastrar e marcar</button>
							</form>
						@endcan

					</div>

					<div class="rh-modal__ft rh-sorteio__ft">
						<span class="rh-small rh-muted rh-sorteio__conta">
							{{ $quantos }} {{ $quantos === 1 ? 'marcado' : 'marcados' }} · {{ $livresMarcadas }} {{ $livresMarcadas === 1 ? 'lugar livre' : 'lugares livres' }}
						</span>
						<button type="button" class="rh-btn rh-btn--primary rh-btn--lg" wire:click="sortear" wire:loading.attr="disabled" @disabled($quantos === 0)>@svg('rh-girar') SORTEAR</button>
					</div>
				@endif
			</div>
		</div>
	@endif
</div>
