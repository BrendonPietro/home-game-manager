<?php

namespace App\Services\Jogo;

use App\Enums\DirecaoPagamento;
use App\Models\Jogo;
use App\Models\Participante;
use App\Support\Dinheiro;
use Illuminate\Support\Collection;

/**
 * Investido, prêmio, pago, recebido e saldo de cada jogador, a partir de `entradas` e `pagamentos`
 * válidos (docs/regras.md, "Cálculos"). Saldo = investido − prêmio − pago + recebido.
 * No cash game o prêmio é o cash-out. Soma em centavos no PHP, como a Calculadora.
 */
class Saldos
{
    /** @return Collection<int, SaldoDoJogador> pelo id do participante */
    public function doJogo(Jogo $jogo): Collection
    {
        return $this->calcular($jogo, $jogo->participantes()->get());
    }

    public function doParticipante(Participante $participante): SaldoDoJogador
    {
        return $this->calcular($participante->jogo, collect([$participante]))->sole();
    }

    /**
     * @param  Collection<int, Participante>  $participantes
     * @return Collection<int, SaldoDoJogador>
     */
    private function calcular(Jogo $jogo, Collection $participantes): Collection
    {
        $ids = $participantes->pluck('id')->all();

        $entradas = $jogo->entradas()->validas()->whereIn('participante_id', $ids)->get(['participante_id', 'valor']);
        $pagamentos = $jogo->pagamentos()->validas()->whereIn('participante_id', $ids)->get(['participante_id', 'direcao', 'valor']);

        $investido = $this->somar($entradas);
        $pago = $this->somar($pagamentos->where('direcao', DirecaoPagamento::JogadorPagou));
        $recebido = $this->somar($pagamentos->where('direcao', DirecaoPagamento::JogadorRecebeu));

        return $participantes->mapWithKeys(fn (Participante $participante) => [
            $participante->id => new SaldoDoJogador(
                participanteId: $participante->id,
                investido: $investido[$participante->id] ?? 0,
                premio: Dinheiro::centavos($jogo->eCashGame() ? $participante->cash_out : $participante->premio),
                pago: $pago[$participante->id] ?? 0,
                recebido: $recebido[$participante->id] ?? 0,
            ),
        ]);
    }

    /** @return Collection<int, int> centavos por participante */
    private function somar(Collection $linhas): Collection
    {
        return $linhas->groupBy('participante_id')
            ->map(fn (Collection $doJogador) => $doJogador->sum(fn ($linha) => Dinheiro::centavos($linha->valor)));
    }
}
