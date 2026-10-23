{{-- Tela 02 (provisória): só a casca. Próximo jogo, números e pendências entram na fase 2. --}}
<x-site.app titulo="Início">
	<x-site.window.topbar />

	<main class="rh-page">
		<div>
			<h1 class="rh-title">Olá, {{ auth('web')->user()->nome_exibicao }}</h1>
			<p class="rh-muted" style="margin:4px 0 0">Você está no site de operação da noite.</p>
		</div>

		<div class="rh-panel">
			<div class="rh-panel__hd"><h2 class="rh-heading">Em construção</h2></div>
			<div class="rh-panel__bd rh-col" style="gap:16px">
				<p style="margin:0">O dashboard, com o próximo jogo, os números do mês e as pendências, entra na fase 2, junto com as telas de antes da noite.</p>

				<div class="rh-row">
					<a class="rh-btn" href="{{ url('/painel') }}">@svg('rh-gear') Abrir o painel</a>

					@if (Route::has('vitrine'))
						<a class="rh-btn rh-btn--ghost" href="{{ route('vitrine') }}">@svg('rh-eye') Vitrine do design system</a>
					@endif
				</div>
			</div>
		</div>
	</main>
</x-site.app>
