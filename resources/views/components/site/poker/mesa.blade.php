{{--
	Mesa de poker com dealer embaixo e os lugares em volta (RHG.pokerTable do design system).
	A posição de partida vem do servidor (PosicoesDaMesa); o mesa.js refaz ao girar e no celular.

	<x-site.poker.mesa :jogo-mesa="$jogoMesa" :ocupantes="$porLugar" :selecionado="$id" acao="clicarAssento" compacta />

	- ocupantes: participantes indexados pelo lugar (com usuario carregado)
	- acao: método Livewire chamado com (jogoMesaId, lugar) ao clicar num assento; sem acao a mesa é só leitura
	- selecionado: id do participante destacado
	- rodapes: [participanteId => ['total' => 'R$ 250', 'fichas' => [['B', null], ['R', 'r'], ['A', 'a']]]] (tela ao vivo)
	- ar / arRetrato: proporção deitada e em pé (padrão do mesa.js: 1,7 / 0,82; celular 1,2 / 0,6); rx / ry: raios em %
	Eliminado e quem fez cash-out aparecem apagados no lugar.
--}}
@props([
	'jogoMesa',
	'ocupantes' => collect(),
	'selecionado' => null,
	'rodapes' => [],
	'acao' => null,
	'compacta' => false,
	'subtitulo' => null,
	'ar' => 1.7,
	'arRetrato' => null,
	'rx' => 42,
	'ry' => 38,
])

@php
	use App\Support\PosicoesDaMesa;

	$lugares = $jogoMesa->lugares;
	$posicoes = PosicoesDaMesa::calcular($lugares, $ar, $rx, $ry);
	$estilo = "--ar:{$ar};--inset:15% 9%";
	foreach ($posicoes as $k => [$x, $y]) {
		$estilo .= ";--x{$k}:{$x}%;--y{$k}:{$y}%";
	}
	$opcoes = array_filter(
		['id' => $jogoMesa->id, 'lugares' => $lugares, 'ar' => $ar, 'arRetrato' => $arRetrato, 'rx' => $rx, 'ry' => $ry],
		fn ($valor) => $valor !== null,
	);
	$posicao = fn (int $k): string => "left:var(--x{$k});top:var(--y{$k})";
@endphp

<div
	{{ $attributes->class(['rh-ptable', 'rh-ptable--compact' => $compacta]) }}
	x-data="mesa(@js($opcoes))"
	x-on:girar-mesa.window="$event.detail === {{ $jogoMesa->id }} && girar()"
	wire:ignore.self
	style="{{ $estilo }}"
>
	<div class="rh-ptable__rail">
		<div class="rh-ptable__felt">
			<div class="rh-ptable__center">
				<span class="rh-ptable__name">{{ $jogoMesa->nome }}</span>
				<span class="rh-label">{{ $subtitulo ?? $lugares.' lugares' }}</span>
				<span class="rh-ptable__suits">♠♥♣♦</span>
			</div>
		</div>
	</div>

	<div class="rh-seat rh-seat--dealer" style="{{ $posicao(0) }}">
		<span class="rh-dealer-btn">D</span>
		<span class="rh-label">Dealer</span>
		<span class="rh-seat__name">{{ $jogoMesa->dealer?->nome_exibicao ?? '—' }}</span>
	</div>

	@for ($lugar = 1; $lugar <= $lugares; $lugar++)
		@php
			$ocupante = $ocupantes->get($lugar);
			$rodape = $ocupante ? ($rodapes[$ocupante->id] ?? null) : null;
			$clique = $acao ? "{$acao}({$jogoMesa->id}, {$lugar})" : null;
		@endphp

		@if ($ocupante)
			<button
				type="button"
				wire:key="assento-{{ $jogoMesa->id }}-{{ $lugar }}"
				@class(['rh-seat', 'is-active' => $selecionado === $ocupante->id, 'rh-seat--out' => $ocupante->estaFora()])
				style="{{ $posicao($lugar) }}"
				@if ($clique) wire:click="{{ $clique }}" wire:loading.attr="disabled" @else disabled @endif
				aria-pressed="{{ $selecionado === $ocupante->id ? 'true' : 'false' }}"
				aria-label="Lugar {{ $lugar }}: {{ $ocupante->usuario->nome_exibicao }}"
			>
				<span class="rh-seat__hd">
					<span class="rh-seat__no">{{ str_pad($lugar, 2, '0', STR_PAD_LEFT) }}</span>
					<x-site.props.avatar :nome="$ocupante->usuario->nome_exibicao" :foto="$ocupante->usuario->foto" tamanho="sm" />
					<span class="rh-seat__name">{{ $ocupante->usuario->nome_exibicao }}</span>
				</span>
				@if ($rodape)
					<span class="rh-seat__ft">
						<span class="rh-seat__total">{{ $rodape['total'] }}</span>
						<span class="rh-seat__chips">
							@foreach ($rodape['fichas'] as [$rotulo, $cor])
								<span @class(['rh-chip', 'rh-chip--'.$cor => $cor])>{{ $rotulo }}</span>
							@endforeach
						</span>
					</span>
				@endif
			</button>
		@else
			<button
				type="button"
				wire:key="assento-{{ $jogoMesa->id }}-{{ $lugar }}"
				class="rh-seat rh-seat--empty"
				style="{{ $posicao($lugar) }}"
				@if ($clique) wire:click="{{ $clique }}" wire:loading.attr="disabled" @else disabled @endif
				aria-label="Lugar {{ $lugar }} vago"
			>
				<span class="rh-seat__name">{{ $lugar }} · vago</span>
			</button>
		@endif
	@endfor
</div>
