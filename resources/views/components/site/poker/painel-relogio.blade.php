@props(['estado'])

{{--
	Painel grande do relógio (v3): título do nível, tempo, situação, blinds e ante (no intervalo, os da
	volta) e o que vem depois. Tudo vem do motor (x-data="relogioTorneio" num contêiner acima, com
	data-relogio), que passa de nível sozinho, também sem internet; o servidor só escreve o primeiro
	texto e o wire:ignore deixa o poll de fora. Usado na TV (e no link do torneio, etapa 3).
--}}
@php
	$pacote = $estado->paraNavegador();
	$nivel = $pacote['niveis'][$pacote['indice']];
	$situacao = match (true) {
		$estado->rodando => null,
		$estado->acabou() => 'Fim da estrutura',
		! $estado->iniciado => 'Aguardando o início',
		default => 'Pausado',
	};
	$esconde = fn ($valor) => filled($valor) ? '' : 'style="display:none"';
@endphp
<section
	{{ $attributes->class(['rh-tv__relogio', 'is-intervalo' => $nivel['intervalo']]) }}
	wire:ignore
	x-bind:class="{ 'is-intervalo': noIntervalo, 'is-aviso': avisando }"
>
	<span class="rh-tv__conexao" x-show="semConexao" x-cloak>Sem conexão · o relógio segue contando</span>
	<span class="rh-tv__aviso" x-show="avisando" x-cloak>AVISO</span>
	<div class="rh-tv__titulo" x-text="titulo">{{ $nivel['titulo'] }}</div>
	<div @class(['rh-tv__tempo', 'is-longo' => $estado->restante >= 3600]) x-bind:class="{ 'is-longo': longo }"><x-site.poker.relogio :estado="$estado" /></div>
	<div class="rh-tv__situacao" x-show="situacao" x-text="situacao" {!! $esconde($situacao) !!}>{{ $situacao }}</div>
	<div class="rh-tv__blinds" x-show="blinds" {!! $esconde($nivel['blinds']) !!}>
		<span class="rh-tv__rotulo" x-show="rotulo" x-text="rotulo" {!! $esconde($nivel['rotulo']) !!}>{{ $nivel['rotulo'] }}</span>
		<span class="v" x-text="blinds">{{ $nivel['blinds'] }}</span>
		<span class="rh-tv__ante" x-show="ante" x-text="ante" {!! $esconde($nivel['ante']) !!}>{{ $nivel['ante'] }}</span>
	</div>
	<div class="rh-tv__proximo" x-show="depois" x-text="depois" {!! $esconde($nivel['depois']) !!}>{{ $nivel['depois'] }}</div>
</section>
