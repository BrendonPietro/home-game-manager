<?php

namespace App\Enums;

use App\Enums\Concerns\TemOpcoes;

/** Fases do torneio durante em_andamento: rebuys → intervalo (Encerrar rebuys) → sem_entradas (Fechar entradas). */
enum FaseTorneio: string
{
    use TemOpcoes;

    case Rebuys = 'rebuys';
    case Intervalo = 'intervalo';
    case SemEntradas = 'sem_entradas';

    public function label(): string
    {
        return match ($this) {
            self::Rebuys => 'Rebuys abertos',
            self::Intervalo => 'Intervalo (add-on)',
            self::SemEntradas => 'Entradas fechadas',
        };
    }
}
