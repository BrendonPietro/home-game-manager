<?php

namespace App\Actions\Jogo;

use App\Actions\Jogo\Concerns\ChecaRegras;
use App\Enums\StatusJogo;
use App\Exceptions\RegraDoJogo;
use App\Models\Jogo;
use App\Models\JogoMesa;
use App\Models\Participante;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;

/**
 * Muda os lugares e o dealer de uma mesa do jogo. Não deixa diminuir abaixo de quem está jogando;
 * quem já saiu do jogo e guardava um lugar que deixa de existir fica sem lugar.
 */
class AjustarMesa
{
    use ChecaRegras;

    public function __invoke(JogoMesa $jogoMesa, int $lugares, ?Usuario $dealer, Usuario $quem): void
    {
        $this->permitir($quem, 'mesas.organizar', 'Você não tem permissão para mudar as mesas.');

        DB::transaction(function () use ($jogoMesa, $lugares, $dealer) {
            $jogo = Jogo::query()->lockForUpdate()->findOrFail($jogoMesa->jogo_id);
            $this->exigirStatus($jogo, [StatusJogo::Agendado, StatusJogo::EmAndamento], 'As mesas só mudam com o jogo agendado ou em andamento.');

            if ($lugares < 2 || $lugares > 12) {
                throw new RegraDoJogo('Uma mesa tem de 2 a 12 lugares.');
            }

            $sentados = $jogoMesa->participantes()->get();
            $maiorOcupado = (int) $sentados->reject->estaFora()->max('lugar');

            if ($maiorOcupado > $lugares) {
                throw new RegraDoJogo("Tem jogador no lugar {$maiorOcupado}: mude-o antes de diminuir a mesa.");
            }

            if ($dealer && ! $dealer->pode_ser_dealer) {
                throw new RegraDoJogo("{$dealer->nome_exibicao} não está marcado como dealer no cadastro.");
            }

            $sentados->filter(fn (Participante $p) => $p->estaFora() && $p->lugar > $lugares)
                ->each->update(['jogo_mesa_id' => null, 'lugar' => null]);

            $jogoMesa->update(['lugares' => $lugares, 'dealer_id' => $dealer?->id]);
        });
    }
}
