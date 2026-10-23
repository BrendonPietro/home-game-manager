{{--
	Fluxo da noite: Antes (conferência, jogadores, mesas) · Durante (ao vivo, resumo) · Final (premiação, resultado, fechamento).
	Etapas antes da atual ficam ✓; só vira link o que o status permite e já tem tela.
	<x-site.window.fluxo-da-noite :jogo="$jogo" etapa="conferencia" />
--}}
@props(['jogo', 'etapa'])

@php
	use App\Support\EtapaDoJogo;

	$etapas = EtapaDoJogo::doJogo($jogo);
	$posicaoAtual = array_search($etapa, $etapas, true);
	$grupos = collect($etapas)->groupBy(fn (string $e) => EtapaDoJogo::ETAPAS[$e][1]);
	$numero = 0;
@endphp

<nav {{ $attributes->class('rh-flow') }} aria-label="Fluxo da noite">
	@foreach ($grupos as $grupo => $doGrupo)
		<div class="rh-flow__grp">
			<span class="rh-label">{{ $grupo }}</span>
			@foreach ($doGrupo as $e)
				@php
					$numero++;
					$posicao = array_search($e, $etapas, true);
					$feita = $posicaoAtual !== false && $posicao < $posicaoAtual;
					$atual = $e === $etapa;
					$url = EtapaDoJogo::permite($jogo, $e) ? EtapaDoJogo::url($jogo, $e) : null;
				@endphp
				@if ($url)
					<a @class(['rh-step', 'is-done' => $feita]) href="{{ $url }}" @if ($atual) aria-current="step" @endif><i>{{ $feita ? '✓' : $numero }}</i>{{ EtapaDoJogo::ETAPAS[$e][0] }}</a>
				@else
					<span @class(['rh-step', 'is-done' => $feita, 'is-off' => ! $atual]) @if ($atual) aria-current="step" @endif><i>{{ $feita ? '✓' : $numero }}</i>{{ EtapaDoJogo::ETAPAS[$e][0] }}</span>
				@endif
			@endforeach
		</div>
	@endforeach
</nav>
