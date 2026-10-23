<!DOCTYPE html>
<html lang="pt-BR">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="csrf-token" content="{{ csrf_token() }}">
	<meta name="theme-color" content="#0b0c0d">
	<meta name="robots" content="noindex, nofollow">

	<title>{{ $titulo ? $titulo.' · ' : '' }}{{ config('app.name') }}</title>

	@vite(['resources/scss/site/index.scss', 'resources/js/site/app.js'])
	@livewireStyles
</head>
<body>
	{{ $slot }}

	@livewireScriptConfig
</body>
</html>
