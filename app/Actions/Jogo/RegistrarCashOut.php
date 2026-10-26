<?php

namespace App\Actions\Jogo;

use App\Actions\Jogo\Concerns\ChecaRegras;
use App\Enums\StatusJogo;
use App\Enums\StatusParticipante;
use App\Enums\TipoItem;
use App\Exceptions\RegraDoJogo;
use App\Models\Jogo;
use App\Models\Participante;
use App\Models\Usuario;
use App\Support\Dinheiro;
use Illuminate\Support\Facades\DB;

/**
 * Cash game: fichas finais × valor da ficha. O jogador sai do jogo (o lugar continua dele até alguém
 * sentar ali). Registrar de novo corrige o valor. Usa a permissão das eliminações.
 */
class RegistrarCashOut
{
    use ChecaRegras;

    public function __invoke(Participante $participante, int $fichas, Usuario $quem): Participante
    {
        $this->permitir($quem, 'eliminacoes.registrar', 'Você não tem permissão para registrar cash-out.');

        if ($fichas < 0) {
            throw new RegraDoJogo('As fichas não podem ser negativas.');
        }

        return DB::transaction(function () use ($participante, $fichas) {
            $jogo = Jogo::query()->lockForUpdate()->findOrFail($participante->jogo_id);
            $this->exigirStatus($jogo, [StatusJogo::EmAndamento], 'Cash-out só com o jogo em andamento.');

            if (! $jogo->eCashGame()) {
                throw new RegraDoJogo('Cash-out é do cash game: no torneio, registre a eliminação.');
            }

            if ($jogo->valor_ficha === null || (float) $jogo->valor_ficha <= 0) {
                throw new RegraDoJogo('O jogo não tem valor da ficha: ajuste no painel.');
            }

            $participante = $jogo->participantes()->with('usuario')->findOrFail($participante->id);

            if (! in_array($participante->status, [StatusParticipante::Jogando, StatusParticipante::Saiu], true)) {
                throw new RegraDoJogo("{$participante->nome()} não está jogando.");
            }

            if (! $participante->entradas()->validas()->where('tipo', TipoItem::Buyin)->exists()) {
                throw new RegraDoJogo("{$participante->nome()} não tem buy-in.");
            }

            $participante->forceFill([
                'fichas_finais' => $fichas,
                'cash_out' => Dinheiro::decimal(Dinheiro::multiplicar($fichas, $jogo->valor_ficha)),
                'status' => StatusParticipante::Saiu,
            ])->save();

            return $participante;
        });
    }
}
