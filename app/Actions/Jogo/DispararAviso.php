<?php

namespace App\Actions\Jogo;

use App\Actions\Jogo\Concerns\ChecaRegras;
use App\Enums\StatusJogo;
use App\Models\Jogo;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;

/**
 * v3: AVISO da tela do relógio do gerenciador. Soma um em `jogos.avisos` e guarda a hora: as telas
 * do relógio (o link, na TV e nos tablets) tocam o som de aviso na próxima atualização (até 2 s).
 * Só com o jogo em andamento; quem dispara: permissão `relogio.controlar`.
 */
class DispararAviso
{
    use ChecaRegras;

    public function __invoke(Jogo $jogo, Usuario $quem): Jogo
    {
        $this->permitir($quem, 'relogio.controlar', 'Só quem controla o relógio dispara o aviso.');

        return DB::transaction(function () use ($jogo): Jogo {
            $jogo = Jogo::query()->lockForUpdate()->findOrFail($jogo->id);
            $this->exigirStatus($jogo, [StatusJogo::EmAndamento], 'O aviso só toca com o jogo em andamento.');

            $jogo->forceFill(['avisos' => $jogo->avisos + 1, 'aviso_em' => now()])->save();

            return $jogo;
        });
    }
}
