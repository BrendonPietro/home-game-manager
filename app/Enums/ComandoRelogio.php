<?php

namespace App\Enums;

/** O que se faz no relógio do torneio (Actions\Jogo\ControlarRelogio). */
enum ComandoRelogio: string
{
    case Continuar = 'continuar'; // também é o INICIAR, na primeira vez
    case Pausar = 'pausar';
    case Proximo = 'proximo';
    case Anterior = 'anterior';
    case Ajustar = 'ajustar';     // ± minutos no nível atual
}
