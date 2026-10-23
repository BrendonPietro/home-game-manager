{{-- Selo do status no site: Ao vivo, Hoje, Agendado, Finalizado. <x-site.props.status-jogo :jogo="$jogo" /> --}}
@props(['jogo'])

@php
	use App\Enums\StatusJogo;

	[$classe, $texto] = match (true) {
		$jogo->status === StatusJogo::EmAndamento => ['rh-badge--live', 'Ao vivo'],
		$jogo->status === StatusJogo::Agendado && $jogo->data?->isToday() => ['rh-badge--next', 'Hoje'],
		$jogo->status === StatusJogo::Agendado => ['rh-badge--plain', 'Agendado'],
		$jogo->status === StatusJogo::Finalizado => ['rh-badge--done', 'Finalizado'],
		default => ['rh-badge--plain', $jogo->status->label()],
	};
@endphp

<span {{ $attributes->class(['rh-badge', $classe]) }}>{{ $texto }}</span>
