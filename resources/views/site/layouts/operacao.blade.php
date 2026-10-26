@php use App\Support\Datas; @endphp

<x-site.app :titulo="$titulo ? $titulo.' · '.$jogo->nome : $jogo->nome">
	<x-site.window.topbar>
		<x-slot:ctx>
			<b>{{ $jogo->nome }}</b>
			<x-site.props.status-jogo :jogo="$jogo" />
			<span class="rh-small rh-muted">{{ $jogo->data->format('d/m/Y') }}@if ($jogo->horario) · {{ Datas::hora($jogo->horario) }}@endif</span>
		</x-slot:ctx>
	</x-site.window.topbar>

	<x-site.window.fluxo-da-noite :jogo="$jogo" :etapa="$etapa" />

	@if ($larga)
		<main class="rh-page rh-page--larga">
			<x-site.props.aviso class="rh-page__aviso" />

			{{ $slot }}
		</main>
	@else
		<main class="rh-page">
			<div class="rh-wrap rh-col" style="gap:16px">
				<x-site.props.aviso />

				{{ $slot }}
			</div>
		</main>
	@endif
</x-site.app>
