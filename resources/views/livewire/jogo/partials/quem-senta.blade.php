{{--
	Lugar vazio clicado (telas 06 e 07, trait Jogo\Concerns\EscolheQuemSenta): quem senta ali. Primeiro os
	confirmados sem lugar (quem já sentou some), depois alguém do cadastro (pela busca) e um jogador novo.
	Recebe $quemSenta = dadosDoLugarVazio().
--}}
@if ($quemSenta)
	<div class="rh-scrim" wire:keydown.escape.window="fecharLugarVazio">
		<div class="rh-modal rh-quem-senta" style="max-width:480px" role="dialog" aria-modal="true" aria-labelledby="quem-senta-titulo">
			<div class="rh-modal__hd">
				<div>
					<div class="rh-label">Quem senta aqui</div>
					<h2 class="rh-heading" id="quem-senta-titulo" style="font-size:24px">{{ $quemSenta['titulo'] }}</h2>
				</div>
				<button type="button" class="rh-btn rh-btn--ghost rh-btn--icon rh-x" wire:click="fecharLugarVazio" aria-label="Fechar">@svg('rh-fechar')</button>
			</div>

			<div class="rh-modal__bd rh-col" style="gap:16px">
				@if ($erro)
					<div class="rh-warn" role="alert">@svg('rh-alerta')<div>{{ $erro }}</div></div>
				@endif

				<div class="rh-search">
					@svg('rh-pesquisar')
					<input class="rh-input" type="search" wire:model.live.debounce.250ms="busca" placeholder="Buscar pelo nome ou apelido" aria-label="Buscar jogador" x-init="$nextTick(() => $el.focus())">
				</div>

				<div class="rh-col" style="gap:8px">
					<span class="rh-label">Confirmados sem lugar · {{ $quemSenta['confirmados']->count() }}</span>
					@forelse ($quemSenta['confirmados'] as $participante)
						<button type="button" class="rh-pcard rh-quem-senta__pessoa" wire:key="senta-{{ $participante->id }}" wire:click="sentarNoLugar({{ $participante->id }})" wire:loading.attr="disabled">
							<x-site.props.avatar :nome="$participante->nome()" :foto="$participante->usuario->foto" tamanho="sm" />
							<span class="rh-pcard__n">{{ $participante->usuario->nome_com_apelido }}</span>
							@if ($quemSenta['antecipado'] && $participante->pagou_antes)
								<span class="rh-badge rh-badge--ok">Pagou antes · +{{ number_format($quemSenta['antecipado'], 0, ',', '.') }}</span>
							@endif
							@svg('rh-chevron-direita', 'rh-faint')
						</button>
					@empty
						<span class="rh-small rh-muted">{{ $busca !== '' ? 'Nenhum confirmado com "'.$busca.'"' : 'Todos os confirmados já têm lugar' }}</span>
					@endforelse
				</div>

				@if ($quemSenta['cadastro']->isNotEmpty())
					<div class="rh-col" style="gap:8px">
						<span class="rh-label">No cadastro, fora da lista</span>
						@foreach ($quemSenta['cadastro'] as $usuario)
							<button type="button" class="rh-pcard rh-quem-senta__pessoa" wire:key="cadastro-{{ $usuario->id }}" wire:click="sentarDoCadastro({{ $usuario->id }})" wire:loading.attr="disabled">
								<x-site.props.avatar :nome="$usuario->nome_exibicao" :foto="$usuario->foto" tamanho="sm" />
								<span class="rh-pcard__n">{{ $usuario->nome_com_apelido }}</span>
								<span class="rh-small rh-faint">confirma e senta</span>
							</button>
						@endforeach
					</div>
				@endif

				@can('usuarios.cadastro_rapido')
					<form class="rh-col rh-quem-senta__novo" style="gap:8px" wire:submit="cadastrarNoLugar">
						<span class="rh-label">Jogador novo</span>
						<div class="rh-row" style="gap:8px">
							<input class="rh-input" style="flex:2;min-width:140px" wire:model="novoNome" placeholder="Nome" aria-label="Nome do jogador novo" autocomplete="off">
							<input class="rh-input" style="flex:1;min-width:100px" wire:model="novoApelido" placeholder="Apelido (opcional)" aria-label="Apelido do jogador novo" autocomplete="off">
						</div>
						<button type="submit" class="rh-btn" wire:loading.attr="disabled">@svg('rh-adicionar') CADASTRAR E SENTAR</button>
					</form>
				@endcan

				@if ($quemSenta['buyin'])
					<div class="rh-small rh-muted">{{ $quemSenta['buyin'] }}</div>
				@endif
			</div>
		</div>
	</div>
@endif
