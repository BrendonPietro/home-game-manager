<?php

namespace App\Services\Jogo;

use App\Models\Jogo;
use App\Support\Fichas;
use Carbon\CarbonInterface;

/**
 * Time chip do torneio: quem faz o buy-in (senta na mesa) até o fim do nível configurado ganha fichas a mais
 * (`jogos.time_chip_fichas`, até o fim do nível `jogos.time_chip_ate_nivel`). Antes de o jogo começar conta
 * como dentro do prazo. Com estrutura de blinds, o nível vem do relógio (um intervalo depois do nível do prazo
 * já é fora); sem estrutura, do nível digitado na tela ao vivo.
 */
class TimeChip
{
    public function __construct(private Relogio $relogio) {}

    /** Fichas do time chip para um buy-in feito agora (0 quando o jogo não tem ou o prazo passou). */
    public function fichasAgora(Jogo $jogo, ?CarbonInterface $agora = null): int
    {
        return $this->valeAgora($jogo, $agora) ? (int) $jogo->time_chip_fichas : 0;
    }

    public function valeAgora(Jogo $jogo, ?CarbonInterface $agora = null): bool
    {
        if (! $jogo->temTimeChip()) {
            return false;
        }

        $ate = max(1, (int) $jogo->time_chip_ate_nivel);
        $estado = $jogo->temRelogio() ? $this->relogio->estado($jogo, $agora) : null;

        if ($estado !== null) {
            // No intervalo, `numero` é nulo e `ultimoNumero` é o nível que acabou de terminar.
            return $estado->numero !== null ? $estado->numero <= $ate : (int) $estado->ultimoNumero < $ate;
        }

        return (int) ($jogo->nivel_atual ?: 1) <= $ate;
    }

    /** "Time chip: 10.000 fichas para quem entrar até o fim do nível 1" */
    public static function texto(Jogo $jogo): ?string
    {
        if (! $jogo->temTimeChip()) {
            return null;
        }

        return 'Time chip: '.self::fichas((int) $jogo->time_chip_fichas).' para quem entrar até o fim do nível '.max(1, (int) $jogo->time_chip_ate_nivel);
    }

    /** 10000 → "10.000 fichas" */
    public static function fichas(int $quantas): string
    {
        return Fichas::quantas($quantas);
    }
}
