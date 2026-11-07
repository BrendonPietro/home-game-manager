{{--
    Favicon e ícones do site e do painel (o painel entra pelo render hook HEAD_END; a impressão do relatório também usa).
    Gerados do logo por `php artisan icones:gerar`. O ?v= muda quando o arquivo muda, para o navegador não ficar com o antigo.
--}}
@php
    $versao = fn (string $arquivo): string => asset($arquivo).'?v='.(is_file(public_path($arquivo)) ? filemtime(public_path($arquivo)) : 1);
@endphp
<link rel="icon" href="{{ $versao('favicon.ico') }}" sizes="16x16 32x32 48x48">
<link rel="icon" type="image/png" href="{{ $versao('icone-192.png') }}" sizes="192x192">
<link rel="apple-touch-icon" href="{{ $versao('apple-touch-icon.png') }}">
