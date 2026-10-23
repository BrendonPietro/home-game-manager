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
		<x-site.props.avatar :nome="$usuario->nome_exibicao" :foto="$usuario->foto" tamanho="sm" />

		<form method="POST" action="{{ route('logout') }}">
			@csrf
			<button type="submit" class="rh-btn rh-btn--sm" title="Sair">@svg('rh-out') Sair</button>
		</form>
	@endif
</header>
