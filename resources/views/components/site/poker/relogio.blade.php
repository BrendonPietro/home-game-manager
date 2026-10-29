@props(['estado', 'segundos' => null])

{{--
	Tempo do relógio do torneio (v2, Services\Jogo\EstadoDoRelogio): conta no navegador entre um poll
	e outro (resources/js/site/relogio.js). O Livewire atualiza data-restante e data-rodando; o texto
	tem wire:ignore para a contagem não pular a cada atualização. Pausado em âmbar, último minuto em vermelho.
	`segundos` conta outra coisa no mesmo ritmo do relógio (na TV, o tempo até o intervalo).
--}}
@php
	use App\Services\Jogo\EstadoDoRelogio;

	$restante = $segundos !== null ? round(max(0, $segundos), 3) : $estado->restanteExato();
@endphp
<span
	{{ $attributes->class(['rh-relogio', 'is-pausado' => ! $estado->rodando]) }}
	x-data="relogio"
	data-restante="{{ $restante }}"
	data-rodando="{{ $estado->rodando ? 1 : 0 }}"
><span wire:ignore x-text="texto" x-bind:class="{ 'is-acabando': acabando }">{{ EstadoDoRelogio::formatar((int) ceil($restante)) }}</span></span>
