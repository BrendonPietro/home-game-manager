<?php

namespace App\Actions\Jogo;

use App\Actions\Jogo\Concerns\ChecaRegras;
use App\Enums\FaseTorneio;
use App\Enums\StatusJogo;
use App\Enums\StatusParticipante;
use App\Enums\TipoItem;
use App\Exceptions\RegraDoJogo;
use App\Models\Eliminacao;
use App\Models\Jogo;
use App\Models\Participante;
use App\Models\Usuario;
use App\Services\Jogo\Posicoes;
use Illuminate\Support\Facades\DB;

/**
 * Torneio. Na fase de rebuys a eliminação é provisória (o jogador pode voltar com rebuy); depois é
 * definitiva. Com as entradas fechadas, dá a posição na hora (último entre os que restam) e o prêmio.
 * O lugar continua do jogador até alguém sentar ali.
 */
class RegistrarEliminacao
{
    use ChecaRegras;

    public function __construct(private Posicoes $posicoes) {}

    public function __invoke(Participante $participante, Usuario $quem): Eliminacao
    {
        $this->permitir($quem, 'eliminacoes.registrar', 'Você não tem permissão para registrar eliminações.');

        return DB::transaction(function () use ($participante, $quem) {
            $jogo = Jogo::query()->lockForUpdate()->findOrFail($participante->jogo_id);
            $this->exigirStatus($jogo, [StatusJogo::EmAndamento], 'Eliminação só com o jogo em andamento.');

            if ($jogo->eCashGame()) {
                throw new RegraDoJogo('Cash game não tem eliminação: registre o cash-out.');
            }

            $participante = $jogo->participantes()->with('usuario')->findOrFail($participante->id);
            $nome = $participante->nome();

            if ($participante->status !== StatusParticipante::Jogando) {
                throw new RegraDoJogo($participante->status === StatusParticipante::Eliminado ? "{$nome} já está eliminado." : "{$nome} não está jogando.");
            }

            if (! $participante->entradas()->validas()->where('tipo', TipoItem::Buyin)->exists()) {
                throw new RegraDoJogo("{$nome} não tem buy-in.");
            }

            // Quem está sentado sem buy-in não está no torneio: não conta para "o último na mesa".
            $emJogo = $jogo->participantes()->naMesa()
                ->whereHas('entradas', fn ($entradas) => $entradas->validas()->where('tipo', TipoItem::Buyin))
                ->count();

            if ($emJogo < 2) {
                throw new RegraDoJogo("{$nome} é o último na mesa.");
            }

            $eliminacao = $jogo->eliminacoes()->create([
                'participante_id' => $participante->id,
                'nivel' => $jogo->nivel_atual,
                'definitiva' => $jogo->fase !== FaseTorneio::Rebuys,
                'registrada_por' => $quem->id,
            ]);

            $participante->forceFill(['status' => StatusParticipante::Eliminado])->save();

            if ($jogo->fase === FaseTorneio::SemEntradas) {
                $this->posicoes->recalcular($jogo);
            }

            return $eliminacao;
        });
    }
}
