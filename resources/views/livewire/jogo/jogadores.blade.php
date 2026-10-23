{{-- Tela 05: jogadores confirmados (docs/prototipo, tela 05). --}}
@php
	use App\Support\EtapaDoJogo;

	$confirmadosIds = $this->confirmados->pluck('usuario_id');
	$total = $this->confirmados->count();
	$sobram = $lugares - $total;
	$organizar = EtapaDoJogo::url($jogo, 'mesas');
	$podeConfirmar = auth('web')->user()->can('jogadores.confirmar');
@endphp

<div class="rh-grid rh-grid--side" style="--side:300px;gap:20px;align-items:start">
	<div class="rh-col" style="gap:16px">
		<div class="rh-row" style="gap:12px">
			<div>
				<div class="rh-label">Antes do {{ $jogo->eTorneio() ? 'torneio' : 'jogo' }} · 2 de 3</div>
				<h1 class="rh-title">Quem vai jogar hoje?</h1>
			</div>
			<span class="rh-spacer"></span>
			<div class="rh-search rh-jogadores__busca">
				@svg('rh-search')
				<input class="rh-input" type="search" wire:model.live.debounce.250ms="busca" placeholder="Buscar jogador" aria-label="Buscar jogador">
			</div>
			@can('usuarios.cadastro_rapido')
				<button type="button" class="rh-btn rh-btn--primary" wire:click="abrirNovo">@svg('rh-plus') NOVO JOGADOR</button>
			@endcan
		</div>

		@if ($erro && ! $novo)
			<div class="rh-warn" role="alert">@svg('rh-alert')<div>{{ $erro }}</div></div>
		@endif

		<div class="rh-row">
			<div class="rh-seg" role="group" aria-label="Filtrar jogadores">
				<button type="button" wire:click="$set('filtro', 'todos')" aria-pressed="{{ $filtro === 'todos' ? 'true' : 'false' }}">Todos ({{ $this->totalAtivos }})</button>
				<button type="button" wire:click="$set('filtro', 'selecionados')" aria-pressed="{{ $filtro === 'selecionados' ? 'true' : 'false' }}">Selecionados ({{ $total }})</button>
				<button type="button" wire:click="$set('filtro', 'frequentes')" aria-pressed="{{ $filtro === 'frequentes' ? 'true' : 'false' }}">Frequentes</button>
			</div>
		</div>

		@if ($this->usuarios->isEmpty())
			<div class="rh-panel"><div class="rh-panel__bd rh-muted">
				{{ $busca !== '' ? 'Ninguém com "'.$busca.'".' : 'Ninguém aqui.' }}
				@can('usuarios.cadastro_rapido') Use NOVO JOGADOR para cadastrar. @endcan
			</div></div>
		@else
			<div class="rh-grid rh-grid--4" style="gap:10px">
				@foreach ($this->usuarios as $usuario)
					@php $on = $confirmadosIds->contains($usuario->id); @endphp
					<button
						type="button"
						wire:key="u-{{ $usuario->id }}"
						@class(['rh-pcard', 'is-on' => $on])
						wire:click="alternar({{ $usuario->id }})"
						wire:loading.attr="disabled"
						aria-pressed="{{ $on ? 'true' : 'false' }}"
						@disabled(! $podeConfirmar)
					>
						<x-site.props.avatar :nome="$usuario->nome_exibicao" :foto="$usuario->foto" />
						<span class="rh-pcard__n">{{ $usuario->nome_exibicao }}</span>
						<span class="rh-tick"></span>
					</button>
				@endforeach
			</div>
		@endif
	</div>

	<aside class="rh-panel">
		<div class="rh-panel__hd">
			<h3 class="rh-heading">Confirmados</h3>
			<span class="rh-spacer"></span>
			<span class="rh-num" style="font-size:24px;font-weight:700">{{ $total }}</span>
		</div>

		<div class="rh-list rh-jogadores__lista">
			@forelse ($this->confirmados as $participante)
				<div class="rh-li" style="padding:8px 16px" wire:key="c-{{ $participante->id }}">
					<x-site.props.avatar :nome="$participante->usuario->nome_exibicao" :foto="$participante->usuario->foto" tamanho="sm" />
					<span style="flex:1;font-weight:600;min-width:0">{{ $participante->usuario->nome_exibicao }}</span>
					@if ($podeConfirmar)
						<button type="button" class="rh-btn rh-btn--ghost rh-btn--icon rh-btn--sm" wire:click="remover({{ $participante->usuario_id }})" wire:loading.attr="disabled" aria-label="Tirar {{ $participante->usuario->nome_exibicao }} da lista">@svg('rh-x')</button>
					@endif
				</div>
			@empty
				<div class="rh-li rh-muted">Ninguém confirmado ainda.</div>
			@endforelse
		</div>

		<div class="rh-panel__ft" style="flex-direction:column">
			<div class="rh-small rh-muted">
				@if ($quantidadeDeMesas === 0)
					Nenhuma mesa ainda: elas se montam na próxima etapa.
				@else
					{{ $quantidadeDeMesas }} {{ $quantidadeDeMesas === 1 ? 'mesa' : 'mesas' }} · {{ $lugares }} lugares · <span @class(['rh-neg' => $sobram < 0])>{{ $sobram >= 0 ? 'sobram '.$sobram : 'faltam '.abs($sobram).' lugares' }}</span>
				@endif
			</div>
			@if ($organizar)
				<a class="rh-btn rh-btn--primary rh-btn--lg rh-btn--block" href="{{ $organizar }}">Organizar mesas @svg('rh-chev-r')</a>
			@else
				<button type="button" class="rh-btn rh-btn--primary rh-btn--lg rh-btn--block" disabled title="A próxima tela ainda não existe">Organizar mesas @svg('rh-chev-r')</button>
			@endif
		</div>
	</aside>

	@if ($novo)
		<div class="rh-scrim" wire:keydown.escape.window="fecharNovo">
			<form class="rh-modal" style="max-width:380px" wire:submit="cadastrar" role="dialog" aria-modal="true" aria-labelledby="novo-titulo">
				<div class="rh-modal__hd">
					<h2 class="rh-heading" id="novo-titulo">Novo jogador</h2>
					<button type="button" class="rh-btn rh-btn--ghost rh-btn--icon rh-x" wire:click="fecharNovo" aria-label="Fechar">@svg('rh-x')</button>
				</div>
				<div class="rh-modal__bd rh-col" style="gap:12px">
					@if ($erro)
						<div class="rh-warn" role="alert">@svg('rh-alert')<div>{{ $erro }}</div></div>
					@endif
					<label class="rh-field">
						<span class="rh-field__label">Nome</span>
						<input class="rh-input" wire:model="novoNome" x-init="$nextTick(() => $el.focus())" autocomplete="off" @error('novoNome') aria-invalid="true" @enderror>
						@error('novoNome')<span class="rh-field__error">{{ $message }}</span>@enderror
					</label>
					<label class="rh-field">
						<span class="rh-field__label">Apelido (opcional)</span>
						<input class="rh-input" wire:model="novoApelido" autocomplete="off">
						<span class="rh-field__help">Aparece na mesa no lugar do nome. Já entra como confirmado nesta noite.</span>
					</label>
				</div>
				<div class="rh-modal__ft">
					<button type="button" class="rh-btn" wire:click="fecharNovo">CANCELAR</button>
					<button type="submit" class="rh-btn rh-btn--primary" wire:loading.attr="disabled">CADASTRAR</button>
				</div>
			</form>
		</div>
	@endif
</div>
