{{-- Paginação do site no estilo do design system: {{ $lista->links('site.partials.paginacao') }} --}}
@if ($paginator->hasPages())
	<nav class="rh-row" style="justify-content:space-between" aria-label="Paginação">
		@if ($paginator->onFirstPage())
			<button class="rh-btn rh-btn--sm" disabled>@svg('rh-chev-l') Anterior</button>
		@else
			<a class="rh-btn rh-btn--sm" href="{{ $paginator->previousPageUrl() }}" rel="prev">@svg('rh-chev-l') Anterior</a>
		@endif

		<span class="rh-small rh-muted">Página {{ $paginator->currentPage() }} de {{ $paginator->lastPage() }}</span>

		@if ($paginator->hasMorePages())
			<a class="rh-btn rh-btn--sm" href="{{ $paginator->nextPageUrl() }}" rel="next">Próxima @svg('rh-chev-r')</a>
		@else
			<button class="rh-btn rh-btn--sm" disabled>Próxima @svg('rh-chev-r')</button>
		@endif
	</nav>
@endif
