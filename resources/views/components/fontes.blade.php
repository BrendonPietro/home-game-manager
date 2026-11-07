{{--
    Pré-carrega as fontes mais usadas (Barlow 400 e 500 no texto, Barlow Condensed 500 nos títulos e números):
    a página já nasce na fonte certa, sem o texto piscar na troca. Site, painel (render hook HEAD_START) e impressão.
--}}
@foreach (['Barlow-400', 'Barlow-500', 'BarlowCondensed-500'] as $fonte)
    <link rel="preload" href="{{ Vite::asset('resources/fonts/barlow/'.$fonte.'.woff2') }}" as="font" type="font/woff2" crossorigin>
@endforeach
