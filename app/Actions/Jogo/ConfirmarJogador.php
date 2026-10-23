<?php

namespace App\Actions\Jogo;

use App\Enums\StatusJogo;
use App\Exceptions\RegraDoJogo;
use App\Models\Jogo;
use App\Models\Participante;
use App\Models\Usuario;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Põe um jogador na lista da noite (participante "confirmado", ainda sem lugar).
 * Vale antes de começar e durante o jogo (quem chega atrasado). Confirmar de novo não duplica.
 */
class ConfirmarJogador
{
    public function __invoke(Jogo $jogo, Usuario $jogador, Usuario $quem): Participante
    {
        if (! $quem->can('jogadores.confirmar')) {
            throw new AuthorizationException('Você não tem permissão para confirmar jogadores.');
        }

        return DB::transaction(function () use ($jogo, $jogador) {
            $jogo = Jogo::query()->lockForUpdate()->findOrFail($jogo->id);

            if (! in_array($jogo->status, [StatusJogo::Agendado, StatusJogo::EmAndamento], true)) {
                throw new RegraDoJogo('A lista de jogadores só muda com o jogo agendado ou em andamento.');
            }

            if (! $jogador->ativo) {
                throw new RegraDoJogo("{$jogador->nome_exibicao} está inativo no cadastro.");
            }

            return $jogo->participantes()->firstOrCreate(['usuario_id' => $jogador->id]);
        });
    }
}
