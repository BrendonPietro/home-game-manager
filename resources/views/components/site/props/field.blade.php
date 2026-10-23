{{-- Campo com rótulo, ajuda e erro de validação: <x-site.props.field label="E-mail" name="email" type="email" /> --}}
@props(['label', 'name', 'type' => 'text', 'ajuda' => null])

@php
	$erro = $errors->first($name);
	$id = $attributes->get('id', 'campo-'.$name);
@endphp

<label class="rh-field" for="{{ $id }}">
	<span class="rh-field__label">{{ $label }}</span>

	<input {{ $attributes->class('rh-input')->merge([
		'id' => $id,
		'type' => $type,
		'name' => $name,
		'value' => $type === 'password' ? null : old($name),
		'aria-invalid' => $erro ? 'true' : null,
		'aria-describedby' => $erro ? $id.'-erro' : null,
	]) }}>

	@if ($erro)
		<span class="rh-field__error" id="{{ $id }}-erro">{{ $erro }}</span>
	@elseif ($ajuda)
		<span class="rh-field__help">{{ $ajuda }}</span>
	@endif
</label>
