<?php

namespace App\Actions\Jogo;

use App\Actions\Jogo\Concerns\ChecaRegras;
use App\Enums\FaseTorneio;
use App\Enums\StatusJogo;
use App\Exceptions\RegraDoJogo;
use App\Models\Jogo;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;

/**
 * Torneio: fase de rebuys → intervalo. Rebuy fecha e add-on abre; quem estava fora e não voltou
 * com rebuy fica eliminado de vez, na ordem em que caiu.
 */
class EncerrarRebuys
{
    use ChecaRegras;

    public function __invoke(Jogo $jogo, Usuario $quem): Jogo
    {
        $this->permitir($quem, 'jogo.fases', 'Só o Admin do site muda a fase do torneio.');

        return DB::transaction(function () use ($jogo) {
            $jogo = Jogo::query()->lockForUpdate()->findOrFail($jogo->id);
            $this->exigirStatus($jogo, [StatusJogo::EmAndamento], 'O jogo não está em andamento.');

            if ($jogo->eCashGame()) {
                throw new RegraDoJogo('Cash game não tem fases.');
            }

            if ($jogo->fase !== FaseTorneio::Rebuys) {
                throw new RegraDoJogo('Os rebuys já foram encerrados.');
            }

            $jogo->eliminacoes()->validas()->emAberto()->update(['definitiva' => true]);

            $jogo->forceFill([
                'fase' => FaseTorneio::Intervalo,
                'rebuys_encerrados_em' => now(),
            ])->save();

            return $jogo;
        });
    }
}
