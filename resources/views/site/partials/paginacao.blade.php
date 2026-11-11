{{-- Paginação do site no estilo do design system: {{ $lista->links('site.partials.paginacao') }} --}}
@if ($paginator->hasPages())
	<nav class="rh-row" style="justify-content:space-between" aria-label="Paginação">
		@if ($paginator->onFirstPage())
			<button class="rh-btn rh-btn--sm" disabled>@svg('rh-chevron-esquerda') Anterior</button>
		@else
			<a class="rh-btn rh-btn--sm" href="{{ $paginator->previousPageUrl() }}" rel="prev">@svg('rh-chevron-esquerda') Anterior</a>
		@endif

		<span class="rh-small rh-muted">Página {{ $paginator->currentPage() }} de {{ $paginator->lastPage() }}</span>

		@if ($paginator->hasMorePages())
			<a class="rh-btn rh-btn--sm" href="{{ $paginator->nextPageUrl() }}" rel="next">Próxima @svg('rh-chevron-direita')</a>
		@else
			<button class="rh-btn rh-btn--sm" disabled>Próxima @svg('rh-chevron-direita')</button>
		@endif
	</nav>
@endif
