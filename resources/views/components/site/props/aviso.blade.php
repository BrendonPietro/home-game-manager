{{-- Aviso de uma linha vindo do redirect (session('aviso')), ex.: etapa que o status não permite. --}}
@if (session('aviso'))
	<div {{ $attributes->class('rh-warn') }} role="status">
		@svg('rh-alert')
		<div>{{ session('aviso') }}</div>
	</div>
@endif
