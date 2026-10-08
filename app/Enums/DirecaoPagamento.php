<?php

namespace App\Enums;

use App\Enums\Concerns\TemOpcoes;

enum DirecaoPagamento: string
{
    use TemOpcoes;

    case JogadorPagou = 'jogador_pagou';
    case JogadorRecebeu = 'jogador_recebeu';

    public function label(): string
    {
        return match ($this) {
            self::JogadorPagou => 'Jogador pagou',
            self::JogadorRecebeu => 'Jogador recebeu',
        };
    }
}
