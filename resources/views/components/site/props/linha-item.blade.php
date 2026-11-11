{{--
	Linha de entrada ou opcional da conferência: ficha, nome, detalhe, fichas e valor.
	<x-site.props.linha-item ficha="B" titulo="Buy-in" :fichas="10000" :valor="$item->valor">Entrada padrão</x-site.props.linha-item>
--}}
@props(['ficha' => null, 'cor' => null, 'titulo', 'fichas' => 0, 'valor' => null, 'desativado' => false])

<div {{ $attributes->class(['rh-item', 'is-off' => $desativado]) }}>
	@if ($ficha)
		<span @class(['rh-chip rh-chip--lg', 'rh-chip--'.$cor => $cor])>{{ $ficha }}</span>
	@else
		<span class="rh-item__sem-ficha" aria-hidden="true">@svg('rh-adicionar')</span>
	@endif

	<div style="min-width:0">
		<div class="rh-item__t">{{ $titulo }}</div>
		@if ($slot->isNotEmpty())
			<div class="rh-small rh-muted">{{ $slot }}</div>
		@endif
	</div>

	<div class="rh-item__fichas">{{ $fichas ? number_format($fichas, 0, ',', '.').' fichas' : '—' }}</div>
	<div class="rh-item__v">{{ $valor !== null && ! $desativado ? \App\Support\Dinheiro::brl($valor) : '—' }}</div>
</div>
