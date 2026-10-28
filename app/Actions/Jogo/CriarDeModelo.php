<?php

namespace App\Actions\Jogo;

use App\Exceptions\RegraDoJogo;
use App\Models\Jogo;
use App\Services\Jogo\CopiaDoJogo;

/**
 * Novo jogo a partir de um torneio modelo (jogos.e_modelo): copia tudo do modelo e recebe só nome,
 * data e horário (sem horário, fica o do modelo). Nasce em rascunho, pronto para conferir e agendar.
 * A permissão (jogos.criar) é do painel, checada na ação do Filament.
 */
class CriarDeModelo
{
    public function __construct(private CopiaDoJogo $copia) {}

    public function __invoke(Jogo $modelo, string $nome, ?string $data, ?string $horario = null): Jogo
    {
        if (! $modelo->e_modelo) {
            throw new RegraDoJogo('Esse jogo não é um modelo.');
        }

        if (trim($nome) === '') {
            throw new RegraDoJogo('Dê um nome ao jogo.');
        }

        return $this->copia->copiar($modelo, array_filter([
            'nome' => trim($nome),
            'data' => $data,
            'horario' => $horario,
        ], fn ($valor) => $valor !== null));
    }
}
