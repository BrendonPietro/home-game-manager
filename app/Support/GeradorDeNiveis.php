<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * "Gerar próximos níveis" das Blinds (painel): continua a estrutura a partir dos últimos níveis.
 *
 * O padrão sai do big blind dos últimos níveis de blinds (intervalo não conta): dobrando, multiplicando
 * pela mesma razão ou somando o mesmo valor. O small blind e o ante seguem a proporção do último nível
 * (100 / 200 · ante 200 → o ante continua igual ao big). Tudo arredondado para valor de ficha
 * (múltiplos de 5, 25, 100, 500 ou 1.000, pelo tamanho), e o big blind sempre sobe.
 *
 * Os níveis entram e saem como o estado do repeater: e_intervalo, small_blind, big_blind, ante,
 * duracao_minutos e nome_intervalo (valores podem vir como texto do formulário).
 */
class GeradorDeNiveis
{
    public const DOBRAR = 'dobrar';

    public const MULTIPLICAR = 'multiplicar';

    public const SOMAR = 'somar';

    public const PADROES = [
        self::DOBRAR => 'Dobrar',
        self::MULTIPLICAR => 'Multiplicar',
        self::SOMAR => 'Somar',
    ];

    /**
     * O padrão dos níveis que já existem, para sugerir no formulário.
     *
     * @param  iterable<array<string, mixed>>  $niveis
     * @return array{tipo: string, valor: int|float, minutos: int, minutos_intervalo: int}
     */
    public static function padrao(iterable $niveis): array
    {
        $niveis = self::normalizar($niveis);
        $blinds = array_values(array_filter($niveis, fn (array $n): bool => ! $n['e_intervalo'] && $n['big_blind'] > 0));
        $intervalos = array_values(array_filter($niveis, fn (array $n): bool => $n['e_intervalo']));
        $grandes = array_column($blinds, 'big_blind');
        $n = count($grandes);

        [$tipo, $valor] = match (true) {
            $n === 0 => [self::DOBRAR, 2],
            $n === 1 => [self::SOMAR, $grandes[0]],
            default => self::detectar($grandes),
        };

        return [
            'tipo' => $tipo,
            'valor' => $valor,
            'minutos' => $n ? $blinds[$n - 1]['duracao_minutos'] : 15,
            'minutos_intervalo' => $intervalos ? end($intervalos)['duracao_minutos'] : 15,
        ];
    }

    /**
     * Os próximos `$quantos` níveis depois dos que existem. Com `$intervaloACada`, entra um intervalo
     * depois de cada tantos níveis gerados (não depois do último).
     *
     * @param  iterable<array<string, mixed>>  $niveis
     * @return list<array{e_intervalo: bool, small_blind: ?int, big_blind: ?int, ante: int, duracao_minutos: int, nome_intervalo: ?string}>
     */
    public static function gerar(
        iterable $niveis,
        int $quantos,
        string $tipo,
        int|float|string $valor,
        int $minutos,
        int $intervaloACada = 0,
        int $minutosIntervalo = 15,
    ): array {
        $blinds = array_values(array_filter(self::normalizar($niveis), fn (array $n): bool => ! $n['e_intervalo'] && $n['big_blind'] > 0));

        if ($blinds === []) {
            throw new InvalidArgumentException('Cadastre ao menos um nível com blinds para gerar os próximos.');
        }

        $valor = (float) str_replace(',', '.', (string) $valor);

        if ($tipo === self::MULTIPLICAR && $valor <= 1) {
            throw new InvalidArgumentException('Para multiplicar, use um número maior que 1 (ex.: 1,5).');
        }

        if ($tipo === self::SOMAR && $valor < 1) {
            throw new InvalidArgumentException('Para somar, use um valor de pelo menos 1.');
        }

        $ultimo = end($blinds);
        $big = $ultimo['big_blind'];
        $razaoSmall = $ultimo['small_blind'] / $big;
        $razaoAnte = $ultimo['ante'] / $big;
        $gerados = [];

        for ($i = 1; $i <= $quantos; $i++) {
            $alvo = match ($tipo) {
                self::DOBRAR => $big * 2,
                self::MULTIPLICAR => $big * $valor,
                self::SOMAR => $big + $valor,
                default => throw new InvalidArgumentException("Padrão desconhecido: {$tipo}."),
            };

            [$small, $proximo] = self::blinds($alvo, $razaoSmall);

            // O arredondamento não pode deixar o nível no mesmo big blind.
            if ($proximo <= $big) {
                [$small, $proximo] = self::blinds($big + self::passo($big), $razaoSmall);
            }

            $big = $proximo;

            $gerados[] = [
                'e_intervalo' => false,
                'small_blind' => $small,
                'big_blind' => $big,
                'ante' => $razaoAnte > 0 ? self::arredondar($big * $razaoAnte) : 0,
                'duracao_minutos' => $minutos,
                'nome_intervalo' => null,
            ];

            if ($intervaloACada > 0 && $i % $intervaloACada === 0 && $i < $quantos) {
                $gerados[] = [
                    'e_intervalo' => true,
                    'small_blind' => null,
                    'big_blind' => null,
                    'ante' => 0,
                    'duracao_minutos' => $minutosIntervalo,
                    'nome_intervalo' => 'Intervalo',
                ];
            }
        }

        return $gerados;
    }

    /** Valor de ficha: múltiplo de 1, 5, 25, 100, 500 ou 1.000, pelo tamanho (237 → 225, 1.640 → 1.600). */
    public static function arredondar(int|float $valor): int
    {
        if ($valor <= 0) {
            return 0;
        }

        $passo = self::passo($valor);

        return (int) max($passo, round($valor / $passo) * $passo);
    }

    /**
     * Small e big perto do alvo. Com o small na metade do big (o normal), o small arredonda no passo do
     * big e o big é o dobro dele (alvo 13.500 → 7.000 / 14.000, não 6.800 / 13.500); com outra proporção,
     * cada um arredonda sozinho.
     *
     * @return array{int, int}
     */
    private static function blinds(int|float $alvo, float $razaoSmall): array
    {
        if (abs($razaoSmall - 0.5) < 0.001) {
            $passo = self::passo($alvo);
            $small = (int) max(1, round($alvo / 2 / $passo) * $passo);

            return [$small, $small * 2];
        }

        $big = self::arredondar($alvo);

        return [min($big, max(1, self::arredondar($big * $razaoSmall))), $big];
    }

    private static function passo(int|float $valor): int
    {
        return match (true) {
            $valor < 10 => 1,
            $valor < 100 => 5,
            $valor < 1000 => 25,
            $valor < 10000 => 100,
            $valor < 100000 => 500,
            default => 1000,
        };
    }

    /**
     * Com 3 ou mais níveis vale o que se repete nos últimos (mesma soma ou mesma razão); sem repetição,
     * a razão do último salto. Com 2, dobrar se dobrou, senão somar a diferença.
     *
     * @param  list<int>  $grandes
     * @return array{string, int|float}
     */
    private static function detectar(array $grandes): array
    {
        [$b0, $b1, $b2] = array_slice([null, ...$grandes], -3);
        $diferenca = $b2 - $b1;
        $razao = $b2 / $b1;

        if ($b0 === null) {
            return $b2 === 2 * $b1 ? [self::DOBRAR, 2] : [self::SOMAR, max(1, $diferenca)];
        }

        if ($diferenca === $b1 - $b0 && $diferenca > 0) {
            return [self::SOMAR, $diferenca];
        }

        if (abs($razao - $b1 / $b0) < 0.01) {
            return $b2 === 2 * $b1 ? [self::DOBRAR, 2] : [self::MULTIPLICAR, round($razao, 2)];
        }

        return $razao > 1 ? [self::MULTIPLICAR, round($razao, 2)] : [self::SOMAR, $b2];
    }

    /**
     * @param  iterable<array<string, mixed>>  $niveis
     * @return list<array{e_intervalo: bool, small_blind: int, big_blind: int, ante: int, duracao_minutos: int}>
     */
    private static function normalizar(iterable $niveis): array
    {
        $saida = [];

        foreach ($niveis as $nivel) {
            $saida[] = [
                'e_intervalo' => (bool) ($nivel['e_intervalo'] ?? false),
                'small_blind' => (int) ($nivel['small_blind'] ?? 0),
                'big_blind' => (int) ($nivel['big_blind'] ?? 0),
                'ante' => (int) ($nivel['ante'] ?? 0),
                'duracao_minutos' => max(1, (int) ($nivel['duracao_minutos'] ?? 15)),
            ];
        }

        return $saida;
    }
}
