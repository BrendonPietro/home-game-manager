<?php

namespace App\Services\Jogo;

/**
 * Números de um jogo, todos em centavos (formatar com Dinheiro::brlCentavos).
 *
 * @phpstan-type Taxa array{nome: string, valor: int}
 * @phpstan-type Premio array{posicao: int, percentual: string, valor: int}
 */
final readonly class ResumoFinanceiro
{
    /**
     * @param  list<Taxa>  $taxas
     * @param  list<Premio>  $premios  vazio no cash game
     */
    public function __construct(
        public int $arrecadacao,
        public int $premiacaoBruta,
        public array $taxas,
        public int $totalTaxas,
        public int $prizePool,
        public int $foraDaPremiacao,
        public int $entradas,
        public int $jogadores,
        public array $premios,
    ) {}

    /** Prêmio de uma posição (0 se a posição não é premiada). */
    public function premio(int $posicao): int
    {
        foreach ($this->premios as $premio) {
            if ($premio['posicao'] === $posicao) {
                return $premio['valor'];
            }
        }

        return 0;
    }
}
