<?php

namespace App\Enums;

use App\Enums\Concerns\TemOpcoes;

/** Ciclo de vida do jogo. cancelado só a partir de rascunho/agendado. */
enum StatusJogo: string
{
    use TemOpcoes;

    case Rascunho = 'rascunho';
    case Agendado = 'agendado';
    case EmAndamento = 'em_andamento';
    case Finalizado = 'finalizado';
    case Cancelado = 'cancelado';

    public function label(): string
    {
        return match ($this) {
            self::Rascunho => 'Rascunho',
            self::Agendado => 'Agendado',
            self::EmAndamento => 'Em andamento',
            self::Finalizado => 'Finalizado',
            self::Cancelado => 'Cancelado',
        };
    }

    public function podeCancelar(): bool
    {
        return in_array($this, [self::Rascunho, self::Agendado], true);
    }
}
