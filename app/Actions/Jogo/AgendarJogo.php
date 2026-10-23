<?php

namespace App\Actions\Jogo;

use App\Enums\MetodoPremiacao;
use App\Enums\StatusJogo;
use App\Enums\TipoItem;
use App\Exceptions\RegraDoJogo;
use App\Models\Jogo;
use App\Services\Jogo\EventoDoJogo;
use App\Support\Dinheiro;
use Illuminate\Support\Facades\DB;

/**
 * Rascunho → agendado: o jogo passa a aparecer no site e no calendário.
 * Exige data, um buy-in ativo com valor e, no torneio com premiação manual, percentuais somando 100%.
 */
class AgendarJogo
{
    public function __construct(private EventoDoJogo $evento) {}

    public function __invoke(Jogo $jogo): Jogo
    {
        return DB::transaction(function () use ($jogo) {
            $jogo = Jogo::query()->lockForUpdate()->findOrFail($jogo->id);

            if ($jogo->status !== StatusJogo::Rascunho) {
                throw new RegraDoJogo('Só um jogo em rascunho pode ser agendado.');
            }

            if ($jogo->e_modelo) {
                throw new RegraDoJogo('Torneio modelo não se agenda: crie um jogo a partir dele.');
            }

            if ($jogo->data === null) {
                throw new RegraDoJogo('Defina a data do jogo antes de agendar.');
            }

            $temBuyin = $jogo->itens()->ativos()->where('tipo', TipoItem::Buyin)->where('valor', '>', 0)->exists();

            if (! $temBuyin) {
                throw new RegraDoJogo('O jogo precisa de pelo menos um buy-in ativo com valor.');
            }

            if ($jogo->eTorneio() && $jogo->metodo_premiacao === MetodoPremiacao::Manual) {
                $soma = $jogo->premiacoes()->get()->sum(fn ($p) => Dinheiro::centavos($p->percentual));

                if ($soma !== 10000) {
                    throw new RegraDoJogo('Os percentuais da premiação precisam somar 100%.');
                }
            }

            $jogo->forceFill(['status' => StatusJogo::Agendado])->save();
            $this->evento->sincronizar($jogo);

            return $jogo;
        });
    }
}
