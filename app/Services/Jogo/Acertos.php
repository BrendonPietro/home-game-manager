<?php

namespace App\Services\Jogo;

use App\Models\Jogo;

/**
 * Sugestão de "quem paga quem" no fechamento (docs/regras.md, "Fechamento e acertos").
 *
 * Os saldos vêm do Saldos: positivo deve, negativo recebe. O caixa (a casa) entra como mais um,
 * com o que falta para fechar a conta: recebe as taxas e o que ficou fora da premiação, menos o
 * que já tem em mãos dos pagamentos da noite (se tiver dinheiro sobrando, ele é quem paga).
 *
 * 1. Primeiro, um devedor (ou dois, ou três) que fecha exatamente o saldo de um credor.
 * 2. Depois, guloso: o maior devedor paga o maior credor até zerar um dos dois.
 */
class Acertos
{
    private const CASA = 'casa';

    public function __construct(private Saldos $saldos) {}

    /** @return list<Transferencia> */
    public function sugerir(Jogo $jogo): array
    {
        $saldos = $this->saldos->doJogo($jogo)
            ->map(fn (SaldoDoJogador $s) => $s->saldo)
            ->filter()
            ->all();

        $casa = -array_sum($saldos);

        if ($casa !== 0) {
            $saldos[self::CASA] = $casa;
        }

        return $this->dividir($saldos);
    }

    /**
     * @param  array<int|string, int>  $saldos  participanteId (ou 'casa') => saldo em centavos; a soma é zero
     * @return list<Transferencia>
     */
    public function dividir(array $saldos): array
    {
        $devem = array_filter($saldos, fn (int $s) => $s > 0);
        $recebem = array_map(fn (int $s) => -$s, array_filter($saldos, fn (int $s) => $s < 0));
        $transferencias = [];

        $pagar = function (int|string $de, int|string $para, int $valor) use (&$devem, &$recebem, &$transferencias): void {
            $transferencias[] = new Transferencia(
                $de === self::CASA ? null : $de,
                $para === self::CASA ? null : $para,
                $valor,
            );

            if (($devem[$de] -= $valor) === 0) {
                unset($devem[$de]);
            }

            if (($recebem[$para] -= $valor) === 0) {
                unset($recebem[$para]);
            }
        };

        while ($devem && $recebem) {
            arsort($devem);
            arsort($recebem);

            if ($exata = $this->combinacaoExata($devem, $recebem)) {
                [$para, $pagadores] = $exata;

                foreach ($pagadores as $de) {
                    $pagar($de, $para, $devem[$de]);
                }

                continue;
            }

            $de = array_key_first($devem);
            $para = array_key_first($recebem);
            $pagar($de, $para, min($devem[$de], $recebem[$para]));
        }

        return $transferencias;
    }

    /**
     * Um credor (do maior para o menor) cujo saldo um, dois ou três devedores fecham certinho.
     *
     * @param  array<int|string, int>  $devem
     * @param  array<int|string, int>  $recebem
     * @return array{0: int|string, 1: list<int|string>}|null
     */
    private function combinacaoExata(array $devem, array $recebem): ?array
    {
        $ids = array_keys($devem);
        $n = count($ids);

        foreach ($recebem as $para => $valor) {
            for ($a = 0; $a < $n; $a++) {
                if ($devem[$ids[$a]] === $valor) {
                    return [$para, [$ids[$a]]];
                }
            }

            for ($a = 0; $a < $n; $a++) {
                for ($b = $a + 1; $b < $n; $b++) {
                    if ($valor === $devem[$ids[$a]] + $devem[$ids[$b]]) {
                        return [$para, [$ids[$a], $ids[$b]]];
                    }
                }
            }

            for ($a = 0; $a < $n; $a++) {
                for ($b = $a + 1; $b < $n; $b++) {
                    for ($c = $b + 1; $c < $n; $c++) {
                        if ($valor === $devem[$ids[$a]] + $devem[$ids[$b]] + $devem[$ids[$c]]) {
                            return [$para, [$ids[$a], $ids[$b], $ids[$c]]];
                        }
                    }
                }
            }
        }

        return null;
    }
}
