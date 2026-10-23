{{-- Marca ♠ Rodrigues: <x-site.props.brand sub="Painel admin" :href="route('home')" /> --}}
@props(['sub' => 'Home Game', 'href' => null])

<{{ $href ? 'a' : 'div' }} {{ $attributes->class('rh-brand')->merge(['href' => $href]) }}>
	<span class="rh-brand__mark" aria-hidden="true">♠</span>
	<div>
		<div class="rh-brand__name">Rodrigues</div>
		<div class="rh-brand__sub">{{ $sub }}</div>
	</div>
</{{ $href ? 'a' : 'div' }}>
