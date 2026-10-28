<?php

namespace App\Services\Relatorios;

use App\Support\Dinheiro;
use Carbon\CarbonInterface;

/**
 * Coluna de um relatório. O valor da linha fica cru (centavos, inteiro, data) e cada saída formata:
 * a tela em reais ("R$ 1.890"), o CSV em "1890,00" e o Excel como número.
 */
final readonly class Coluna
{
    public const TEXTO = 'texto';

    public const INTEIRO = 'inteiro';

    /** centavos */
    public const DINHEIRO = 'dinheiro';

    /** centavos, com sinal na tela (+R$ 795 / −R$ 200) */
    public const RESULTADO = 'resultado';

    public const DATA = 'data';

    public function __construct(
        public string $chave,
        public string $rotulo,
        public string $tipo = self::TEXTO,
    ) {}

    public function numerica(): bool
    {
        return in_array($this->tipo, [self::INTEIRO, self::DINHEIRO, self::RESULTADO], true);
    }

    /** Valor como aparece na tela. */
    public function formatar(mixed $valor): string
    {
        if ($valor === null || $valor === '') {
            return '—';
        }

        return match ($this->tipo) {
            self::DINHEIRO => Dinheiro::brlCentavos((int) $valor),
            self::RESULTADO => Dinheiro::brlCentavos((int) $valor, true),
            self::DATA => $valor instanceof CarbonInterface ? $valor->format('d/m/Y') : (string) $valor,
            self::INTEIRO => number_format((int) $valor, 0, ',', '.'),
            default => (string) $valor,
        };
    }
}
