{{--
	Topbar do site: marca, jogo atual (slot ctx), ações (slot acoes), quem está logado e o menu
	(Jogos, Acessar o painel em outra aba e Sair).
	<x-site.window.topbar><x-slot:ctx>…</x-slot:ctx></x-site.window.topbar>
--}}
@props(['ctx' => null, 'acoes' => null])

@php
	$usuario = auth('web')->user();
	// O painel é outro login: o link aparece para quem tem conta lá (contas do clube) ou já entrou nele.
	$painel = $usuario && (auth('painel')->check() || $usuario->temContaNoPainel());
@endphp

<header {{ $attributes->class('rh-top') }}>
	<x-site.props.brand :href="route('home')" />

	@if ($ctx)
		<div class="rh-top__ctx">{{ $ctx }}</div>
	@endif

	<span class="rh-spacer"></span>

	{{ $acoes }}

	@if ($usuario)
		{{-- Quem está logado: as iniciais dentro da ficha (App\Support\AvatarDeFicha); com foto, a foto. --}}
		@if ($usuario->foto)
			<x-site.props.avatar :nome="$usuario->nome_exibicao" :foto="$usuario->foto" tamanho="sm" />
		@else
			<span class="rh-avatar-ficha" title="{{ $usuario->nome_exibicao }}">{!! \App\Support\AvatarDeFicha::svg($usuario->nome) !!}</span>
		@endif

		<div class="rh-top__menu" x-data="{ aberto: false }" x-on:keydown.escape.window="aberto = false" x-on:click.outside="aberto = false">
			<button type="button" class="rh-btn rh-btn--icon rh-top__abrir" x-on:click="aberto = ! aberto" x-bind:aria-expanded="aberto" aria-haspopup="menu" aria-controls="menu-do-site" aria-label="Menu">@svg('rh-menu')</button>

			<div class="rh-top__lista" id="menu-do-site" role="menu" x-show="aberto" x-transition.opacity x-cloak>
				<div class="rh-top__quem">
					<span class="rh-top__nome">{{ $usuario->nome }}</span>
					@if ($usuario->login || $usuario->email)
						<span class="rh-small rh-muted">{{ $usuario->login ?? $usuario->email }}</span>
					@endif
				</div>

				<a class="rh-top__item" role="menuitem" href="{{ route('jogos.index') }}">@svg('rh-lista') Jogos</a>

				@if ($painel)
					<a class="rh-top__item" role="menuitem" href="{{ url('/painel') }}" target="_blank" rel="noopener">@svg('rh-painel') Acessar o painel</a>
				@endif

				<form method="POST" action="{{ route('logout') }}">
					@csrf
					<button type="submit" class="rh-top__item" role="menuitem">@svg('rh-sair') Sair</button>
				</form>
			</div>
		</div>
	@endif
</header>
