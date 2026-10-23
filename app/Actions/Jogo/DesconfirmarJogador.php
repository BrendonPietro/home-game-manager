<?php

namespace App\Actions\Jogo;

use App\Enums\StatusJogo;
use App\Exceptions\RegraDoJogo;
use App\Models\Jogo;
use App\Models\Usuario;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Tira um jogador da lista da noite (e do lugar, se tinha). Só antes de ele ter qualquer
 * entrada, pagamento ou eliminação: depois disso o caixa já o conhece e nada é apagado.
 */
class DesconfirmarJogador
{
    public function __invoke(Jogo $jogo, Usuario $jogador, Usuario $quem): void
    {
        if (! $quem->can('jogadores.confirmar')) {
            throw new AuthorizationException('Você não tem permissão para mudar a lista de jogadores.');
        }

        DB::transaction(function () use ($jogo, $jogador) {
            $jogo = Jogo::query()->lockForUpdate()->findOrFail($jogo->id);

            if (! in_array($jogo->status, [StatusJogo::Agendado, StatusJogo::EmAndamento], true)) {
                throw new RegraDoJogo('A lista de jogadores só muda com o jogo agendado ou em andamento.');
            }

            $participante = $jogo->participantes()->where('usuario_id', $jogador->id)->first();

            if ($participante === null) {
                return;
            }

            $temMovimento = $participante->entradas()->exists()
                || $participante->pagamentos()->exists()
                || $participante->eliminacoes()->exists();

            if ($temMovimento) {
                throw new RegraDoJogo("{$jogador->nome_exibicao} já tem lançamento nesta noite: não sai da lista.");
            }

            $participante->delete();
        });
    }
}
