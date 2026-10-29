<?php

namespace App\Actions\Jogo;

use App\Actions\Jogo\Concerns\ChecaRegras;
use App\Enums\FaseTorneio;
use App\Enums\StatusJogo;
use App\Enums\StatusParticipante;
use App\Enums\TipoItem;
use App\Exceptions\RegraDoJogo;
use App\Models\Jogo;
use App\Models\Usuario;
use App\Services\Jogo\Relogio;
use Illuminate\Support\Facades\DB;

/**
 * Agendado → em andamento (botão Iniciar na tela de mesas). Torneio começa na fase de rebuys.
 * Quem está sentado passa a "jogando"; confirmado sem lugar continua esperando e senta depois.
 */
class IniciarJogo
{
    use ChecaRegras;

    public function __construct(private Relogio $relogio) {}

    public function __invoke(Jogo $jogo, Usuario $quem): Jogo
    {
        $this->permitir($quem, 'jogo.fases', 'Só o Admin do site inicia o jogo.');

        return DB::transaction(function () use ($jogo) {
            $jogo = Jogo::query()->lockForUpdate()->findOrFail($jogo->id);
            $this->exigirStatus($jogo, [StatusJogo::Agendado], 'Só jogo agendado pode ser iniciado.');

            $sentados = $jogo->participantes()->whereNotNull('jogo_mesa_id')->where('status', StatusParticipante::Confirmado);

            if ($sentados->count() < 2) {
                throw new RegraDoJogo('Sente pelo menos 2 jogadores antes de iniciar.');
            }

            if (! $jogo->itens()->ativos()->where('tipo', TipoItem::Buyin)->exists()) {
                throw new RegraDoJogo('O jogo não tem buy-in ativo: ajuste no painel.');
            }

            $sentados->update(['status' => StatusParticipante::Jogando]);

            $jogo->forceFill([
                'status' => StatusJogo::EmAndamento,
                'fase' => $jogo->eTorneio() ? FaseTorneio::Rebuys : null,
                'iniciado_em' => now(),
            ])->save();

            $this->relogio->preparar($jogo); // com estrutura: nível 1 parado, até apertar INICIAR

            return $jogo;
        });
    }
}
