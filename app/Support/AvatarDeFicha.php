<?php

namespace App\Support;

/**
 * Avatar de quem está logado (topo do site e menu da conta do painel): a ficha de poker de
 * resources/svg/icones/ficha.svg com as iniciais no miolo, no lugar dos naipes. O miolo é o centro do desenho
 * (viewBox) com raio de 27% da largura: trocar o ficha.svg por outra ficha centrada continua funcionando.
 */
class AvatarDeFicha
{
    /** Raio do miolo escondido, em fração da largura do desenho. */
    private const MIOLO = 0.27;

    /** "Brendon Pietro" → "BP"; "Operador (teste)" → "OT". */
    public static function iniciais(string $nome): string
    {
        return mb_strtoupper(collect(preg_split('/[^\p{L}\p{N}]+/u', $nome, -1, PREG_SPLIT_NO_EMPTY))
            ->map(fn (string $palavra): string => mb_substr($palavra, 0, 1))
            ->take(2)
            ->implode(''));
    }

    /**
     * O SVG inteiro. Sem $cor, usa currentColor (cor do texto ao redor); com $cor, fixa a cor (para usar como imagem).
     */
    public static function svg(string $nome, ?string $cor = null): string
    {
        $ficha = file_get_contents(resource_path('svg/icones/ficha.svg'));
        preg_match('/viewBox="([^"]+)"/', $ficha, $caixa);
        [$x, $y, $largura, $altura] = array_map('floatval', preg_split('/[\s,]+/', trim($caixa[1] ?? '0 0 100 100')));
        $miolo = preg_replace(['/^.*?<svg[^>]*>/s', '/<\/svg>\s*$/s'], '', $ficha);

        $cx = $x + $largura / 2;
        $cy = $y + $altura / 2;
        $raio = round($largura * self::MIOLO, 2);
        $mascara = 'rh-ficha-'.substr(md5(uniqid('', true)), 0, 8);
        $estilo = $cor ? ' style="color:'.e($cor).'"' : '';
        $letras = e(self::iniciais($nome));
        $tamanho = round($largura * (mb_strlen($letras) > 1 ? 0.28 : 0.34), 2);

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="'.e($caixa[1] ?? '0 0 100 100').'" fill="none" aria-hidden="true"'.$estilo.'>'
            .'<defs><mask id="'.$mascara.'"><rect x="'.$x.'" y="'.$y.'" width="'.$largura.'" height="'.$altura.'" fill="white"/>'
            .'<circle cx="'.$cx.'" cy="'.$cy.'" r="'.$raio.'" fill="black"/></mask></defs>'
            .'<g mask="url(#'.$mascara.')">'.$miolo.'</g>'
            .'<text x="'.$cx.'" y="'.$cy.'" text-anchor="middle" dominant-baseline="central" font-size="'.$tamanho.'" font-weight="600"'
            .' font-family="Barlow Condensed, Arial Narrow, sans-serif" fill="currentColor">'.$letras.'</text>'
            .'</svg>';
    }

    /** Como imagem (o avatar do Filament é um <img>): cor fixa, já que currentColor não chega dentro da imagem. */
    public static function dataUri(string $nome, string $cor): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode(self::svg($nome, $cor));
    }
}
