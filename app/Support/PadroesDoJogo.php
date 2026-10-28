<?php

namespace App\Support;

use App\Models\Configuracao;

/**
 * Padrões de um jogo novo (tela 25), gravados em `configuracoes` (chave `jogo.padroes`): horário,
 * lugares, mesa final, valor e fichas do buy-in, rebuy e add-on padrão e uma taxa em % (ex.: casa 10%).
 * Valem só ao criar o jogo (ou modelo); quem já existe não muda.
 */
class PadroesDoJogo
{
    public const CHAVE = 'jogo.padroes';

    public const INICIAIS = [
        'horario' => '20:00',
        'lugares_por_mesa' => 9,
        'tamanho_mesa_final' => 9,
        'buyin_valor' => null,
        'buyin_fichas' => 0,
        'rebuy_valor' => null,
        'rebuy_fichas' => 0,
        'addon_valor' => null,
        'addon_fichas' => 0,
        'taxa_nome' => null,
        'taxa_percentual' => null,
    ];

    /** @return array<string, mixed> */
    public static function todos(): array
    {
        return array_replace(self::INICIAIS, array_intersect_key((array) Configuracao::valor(self::CHAVE, []), self::INICIAIS));
    }

    public static function valor(string $campo): mixed
    {
        return self::todos()[$campo] ?? null;
    }

    /** @param  array<string, mixed>  $padroes */
    public static function gravar(array $padroes): void
    {
        Configuracao::definir(self::CHAVE, array_intersect_key($padroes, self::INICIAIS));
    }
}
