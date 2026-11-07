<?php

namespace App\Enums;

use App\Enums\Concerns\TemOpcoes;

/** O que foi feito no jogo, no histórico de alterações (`alteracoes.tipo`). */
enum TipoAlteracao: string
{
    use TemOpcoes;

    case Iniciou = 'iniciou';
    case AbriuIntervalo = 'abriu_intervalo';
    case FechouEntradas = 'fechou_entradas';
    case VoltouAosRebuys = 'voltou_aos_rebuys';
    case ReabriuEntradas = 'reabriu_entradas';
    case DefiniuResultado = 'definiu_resultado';
    case Finalizou = 'finalizou';
    case Desfez = 'desfez';
    case DesfezPagamento = 'desfez_pagamento';
    case AcertoFeito = 'acerto_feito';
    case DesfezAcerto = 'desfez_acerto';

    public function label(): string
    {
        return match ($this) {
            self::Iniciou => 'Início',
            self::AbriuIntervalo => 'Intervalo aberto',
            self::FechouEntradas => 'Entradas fechadas',
            self::VoltouAosRebuys => 'Voltou aos rebuys',
            self::ReabriuEntradas => 'Entradas reabertas',
            self::DefiniuResultado => 'Resultado',
            self::Finalizou => 'Finalizado',
            self::Desfez => 'Desfeito',
            self::DesfezPagamento => 'Pagamento desfeito',
            self::AcertoFeito => 'Acerto feito',
            self::DesfezAcerto => 'Acerto desfeito',
        };
    }

    /** Correção (voltar fase, desfazer): aparece em destaque no histórico. */
    public function correcao(): bool
    {
        return in_array($this, [self::VoltouAosRebuys, self::ReabriuEntradas, self::Desfez, self::DesfezPagamento, self::DesfezAcerto], true);
    }
}
