<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Uma regra do jogo impediu a ação (ex.: agendar sem buy-in, cancelar jogo em andamento).
 * A mensagem é para quem está usando o sistema: mostrar como está.
 */
class RegraDoJogo extends RuntimeException {}
