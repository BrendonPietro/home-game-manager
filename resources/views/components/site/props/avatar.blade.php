{{-- Avatar com foto ou iniciais: <x-site.props.avatar :nome="$usuario->nome" :foto="$usuario->foto" tamanho="sm" /> --}}
@props(['nome', 'foto' => null, 'tamanho' => null])

@php
	$iniciais = collect(preg_split('/\s+/', trim($nome)))
		->map(fn ($palavra) => mb_substr($palavra, 0, 1))
		->take(2)
		->implode('');
@endphp

<span {{ $attributes->class(['rh-avatar', 'rh-avatar--'.$tamanho => $tamanho]) }} title="{{ $nome }}">
	@if ($foto)
		<img src="{{ Storage::url($foto) }}" alt="">
	@else
		{{ mb_strtoupper($iniciais) }}
	@endif
</span>
