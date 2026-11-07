{{--
	Topbar do site: marca, jogo atual (slot ctx), ações (slot acoes), quem está logado e Sair.
	<x-site.window.topbar><x-slot:ctx>…</x-slot:ctx></x-site.window.topbar>
--}}
@props(['ctx' => null, 'acoes' => null])

@php($usuario = auth('web')->user())

<header {{ $attributes->class('rh-top') }}>
	<x-site.props.brand :href="route('home')" />

	@if ($ctx)
		<div class="rh-top__ctx">{{ $ctx }}</div>
	@endif

	<span class="rh-spacer"></span>

	{{ $acoes }}

	@if ($usuario)
		<a class="rh-btn rh-btn--ghost rh-btn--sm rh-top__link" href="{{ route('jogos.index') }}" aria-label="Jogos">@svg('rh-list') <span>Jogos</span></a>
		<x-site.props.avatar :nome="$usuario->nome_exibicao" :foto="$usuario->foto" tamanho="sm" />

		<form method="POST" action="{{ route('logout') }}">
			@csrf
			<button type="submit" class="rh-btn rh-btn--sm rh-top__sair" title="Sair" aria-label="Sair">@svg('rh-out') <span>Sair</span></button>
		</form>
	@endif
</header>
