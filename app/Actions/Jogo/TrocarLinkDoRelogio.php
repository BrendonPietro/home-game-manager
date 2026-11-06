<?php

namespace App\Actions\Jogo;

use App\Actions\Jogo\Concerns\ChecaRegras;
use App\Models\Jogo;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;

/**
 * v3: gera outro código para o link do relógio (`/relogio/{codigo}`); o link antigo para de abrir
 * (quem estava com ele aberto vê "link trocado"). Para quando o link vazou para quem não devia.
 * Quem troca: permissão `relogio.controlar`.
 */
class TrocarLinkDoRelogio
{
    use ChecaRegras;

    public function __invoke(Jogo $jogo, Usuario $quem): Jogo
    {
        $this->permitir($quem, 'relogio.controlar', 'Só quem controla o relógio troca o link.');

        return DB::transaction(function () use ($jogo): Jogo {
            $jogo = Jogo::query()->lockForUpdate()->findOrFail($jogo->id);
            $jogo->forceFill(['relogio_codigo' => Jogo::novoCodigoDoRelogio()])->save();

            return $jogo;
        });
    }
}
