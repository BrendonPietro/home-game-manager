<?php

namespace App\Actions\Jogo;

use App\Actions\Jogo\Concerns\ChecaRegras;
use App\Enums\FaseTorneio;
use App\Enums\StatusJogo;
use App\Enums\TipoAlteracao;
use App\Exceptions\RegraDoJogo;
use App\Models\Jogo;
use App\Models\Usuario;
use App\Services\Jogo\Historico;
use Illuminate\Support\Facades\DB;

/**
 * Torneio: fase de rebuys → intervalo (botão ABRIR INTERVALO). O add-on abre e o rebuy continua
 * aberto: quem caiu pode respirar e voltar no intervalo. As eliminações continuam provisórias; ficam
 * definitivas no FECHAR ENTRADAS, que fecha buy-in, rebuy e add-on de uma vez.
 */
class AbrirIntervalo
{
    use ChecaRegras;

    public function __construct(private Historico $historico) {}

    public function __invoke(Jogo $jogo, Usuario $quem): Jogo
    {
        $this->permitir($quem, 'jogo.fases', 'Só o Admin do site muda a fase do torneio.');

        return DB::transaction(function () use ($jogo, $quem) {
            $jogo = Jogo::query()->lockForUpdate()->findOrFail($jogo->id);
            $this->exigirStatus($jogo, [StatusJogo::EmAndamento], 'O jogo não está em andamento.');

            if ($jogo->eCashGame()) {
                throw new RegraDoJogo('Cash game não tem fases.');
            }

            if ($jogo->fase !== FaseTorneio::Rebuys) {
                throw new RegraDoJogo('O intervalo já foi aberto.');
            }

            $jogo->forceFill([
                'fase' => FaseTorneio::Intervalo,
                'intervalo_aberto_em' => now(),
            ])->save();

            $this->historico->registrar($jogo, $quem, TipoAlteracao::AbriuIntervalo, 'Abriu o intervalo (add-on aberto; o rebuy continua)');

            return $jogo;
        });
    }
}
