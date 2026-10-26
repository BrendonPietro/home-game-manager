<?php

namespace App\Services\Jogo;

/**
 * Conta de um jogador no jogo, em centavos (formatar com Dinheiro::brlCentavos).
 * Saldo positivo: o jogador deve ao caixa. Negativo: o caixa deve a ele.
 */
final readonly class SaldoDoJogador
{
    public int $saldo;

    /** @param  int  $premio  prêmio da posição no torneio; cash-out no cash game */
    public function __construct(
        public int $participanteId,
        public int $investido,
        public int $premio,
        public int $pago,
        public int $recebido,
    ) {
        $this->saldo = $investido - $premio - $pago + $recebido;
    }

    /** Lucro ou prejuízo da noite: prêmio (ou cash-out) − investido. */
    public function resultado(): int
    {
        return $this->premio - $this->investido;
    }

    public function quitado(): bool
    {
        return $this->saldo === 0;
    }
}
