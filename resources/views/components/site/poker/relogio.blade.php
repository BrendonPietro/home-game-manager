@props(['estado', 'segundos' => null])

{{--
	Tempo do relógio do torneio (v3): precisa estar dentro de um contêiner com o motor
	(x-data="relogioTorneio" e data-relogio, resources/js/site/relogio-torneio.js), que conta e passa de
	nível sozinho. Com `segundos`, mostra o tempo até o intervalo no mesmo ritmo. O servidor só escreve o
	primeiro texto; wire:ignore deixa o poll fora da contagem. Pausado em âmbar, último minuto em vermelho.
--}}
@php
	use App\Services\Jogo\EstadoDoRelogio;

	$intervalo = $segundos !== null;
	$texto = EstadoDoRelogio::formatar((int) ceil(max(0, $intervalo ? $segundos : $estado->restanteExato())));
@endphp
<span
	{{ $attributes->class(['rh-relogio', 'is-pausado' => ! $estado->rodando]) }}
	wire:ignore
	x-bind:class="{ 'is-pausado': ! rodando }"
><span @if ($intervalo) x-text="intervaloEmTexto" @else x-text="tempo" x-bind:class="{ 'is-acabando': acabando }" @endif>{{ $texto }}</span></span>
