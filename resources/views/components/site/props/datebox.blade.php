{{-- Caixa de data (mês + dia): <x-site.props.datebox :data="$jogo->data" pequena /> --}}
@props(['data', 'pequena' => false])

<div {{ $attributes->class(['rh-datebox', 'rh-datebox--sm' => $pequena]) }}>
	<span>{{ \App\Support\Datas::mes($data) }}</span>
	<b>{{ $data->format('d') }}</b>
</div>
