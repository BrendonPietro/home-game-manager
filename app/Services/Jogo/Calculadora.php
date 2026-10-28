<?php

namespace App\Services\Jogo;

use App\Enums\CalculoTaxa;
use App\Enums\DestinoItem;
use App\Enums\MetodoPremiacao;
use App\Enums\TipoItem;
use App\Models\Entrada;
use App\Models\Item;
use App\Models\Jogo;
use App\Models\RegraPremiacao;
use App\Models\Taxa;
use App\Support\Dinheiro;
use Illuminate\Support\Collection;

/**
 * Arrecadação, premiação bruta, taxas, prize pool e prêmios (docs/regras.md, "Cálculos").
 * Tudo em centavos inteiros. Referência: Poker Night #18 = R$ 2.100 / 210 / 1.890 → 945 / 567 / 378.
 *
 * Um lançamento é o que importa de uma entrada para a conta: tipo, valor em centavos e destino.
 */
class Calculadora
{
    /** Números do jogo a partir das entradas válidas (canceladas não contam). */
    public function doJogo(Jogo $jogo): ResumoFinanceiro
    {
        $entradas = $jogo->entradas()->validas()->get(['tipo', 'valor', 'destino', 'participante_id']);

        // Jogador = quem tem pelo menos um buy-in válido.
        $jogadores = $entradas->where('tipo', TipoItem::Buyin)->pluck('participante_id')->unique()->count();

        return $this->calcular(
            $jogo,
            $entradas->map(fn (Entrada $entrada): array => [
                'tipo' => $entrada->tipo,
                'valor' => Dinheiro::centavos($entrada->valor),
                'destino' => $entrada->destino,
            ]),
            $jogadores,
        );
    }

    /**
     * Simulação pelo cadastro: N jogadores com o buy-in padrão, N rebuys e N add-ons padrão,
     * mais os opcionais obrigatórios. Usada no prize pool estimado (dashboard, conferência, painel).
     *
     * @param  Collection<int, Item>|null  $itens  itens ainda não gravados (o formulário do painel); as taxas
     *                                             e a premiação manual vêm das relações `taxas` e `premiacoes`
     */
    public function simular(Jogo $jogo, int $jogadores, int $rebuys = 0, int $addons = 0, ?Collection $itens = null): ResumoFinanceiro
    {
        $itens = $itens?->filter(fn (Item $item): bool => (bool) $item->ativo)->values() ?? $jogo->itens()->ativos()->get();
        $padrao = fn (TipoItem $tipo): ?Item => $itens->first(fn (Item $i) => $i->tipo === $tipo && $i->padrao)
            ?? $itens->first(fn (Item $i) => $i->tipo === $tipo);

        $lancamentos = collect();
        $lancar = function (?Item $item, int $vezes) use ($lancamentos): void {
            for ($i = 0; $item && $i < $vezes; $i++) {
                $lancamentos->push(['tipo' => $item->tipo, 'valor' => Dinheiro::centavos($item->valor), 'destino' => $item->destino]);
            }
        };

        $lancar($padrao(TipoItem::Buyin), $jogadores);
        $lancar($padrao(TipoItem::Rebuy), $rebuys);
        $lancar($padrao(TipoItem::Addon), $addons);

        $quantasEntradas = $lancamentos->count();

        foreach ($itens->where('tipo', TipoItem::Opcional)->where('obrigatorio', true) as $opcional) {
            $lancar($opcional, $opcional->porJogador() ? $jogadores : $quantasEntradas);
        }

        return $this->calcular($jogo, $lancamentos, $jogadores);
    }

    /**
     * @param  Collection<int, array{tipo: TipoItem, valor: int, destino: DestinoItem}>  $lancamentos
     */
    public function calcular(Jogo $jogo, Collection $lancamentos, int $jogadores): ResumoFinanceiro
    {
        $arrecadacao = $lancamentos->sum('valor');

        // A fração do destino vale sobre a soma de cada destino (não lançamento a lançamento).
        $premiacaoBruta = $lancamentos->groupBy(fn (array $l): string => $l['destino']->value)
            ->sum(fn (Collection $doDestino, string $destino): int => intdiv(
                $doDestino->sum('valor') * DestinoItem::from($destino)->percentual(),
                100,
            ));
        $entradas = $lancamentos->filter(fn (array $l): bool => $l['tipo']->eEntrada())->count();

        $taxas = $jogo->taxas->map(fn (Taxa $taxa): array => [
            'nome' => $taxa->nome,
            'valor' => $this->taxa($taxa, $premiacaoBruta, $jogadores, $entradas),
        ])->values()->all();

        $totalTaxas = array_sum(array_column($taxas, 'valor'));
        $prizePool = max(0, $premiacaoBruta - $totalTaxas);

        return new ResumoFinanceiro(
            arrecadacao: $arrecadacao,
            premiacaoBruta: $premiacaoBruta,
            taxas: $taxas,
            totalTaxas: $totalTaxas,
            prizePool: $prizePool,
            foraDaPremiacao: $arrecadacao - $premiacaoBruta,
            entradas: $entradas,
            jogadores: $jogadores,
            premios: $jogo->eTorneio() ? $this->premios($jogo, $prizePool, $jogadores) : [],
        );
    }

    private function taxa(Taxa $taxa, int $premiacaoBruta, int $jogadores, int $entradas): int
    {
        $valor = Dinheiro::centavos($taxa->valor);

        return match ($taxa->calculo) {
            CalculoTaxa::Fixo => $valor,
            CalculoTaxa::Percentual => Dinheiro::percentual($premiacaoBruta, $taxa->valor),
            CalculoTaxa::PorUnidade => $valor * max(1, (int) $taxa->quantidade),
            CalculoTaxa::PorJogador => $valor * $jogadores,
            CalculoTaxa::PorEntrada => $valor * $entradas,
        };
    }

    /**
     * Prize pool × percentual de cada posição, para baixo; a sobra do arredondamento vai para o 1º.
     *
     * @return list<array{posicao: int, percentual: string, valor: int}>
     */
    private function premios(Jogo $jogo, int $prizePool, int $jogadores): array
    {
        $percentuais = $this->percentuais($jogo, $jogadores);
        $premios = [];

        foreach ($percentuais as $indice => $percentual) {
            $premios[] = [
                'posicao' => $indice + 1,
                'percentual' => Dinheiro::decimal(Dinheiro::centavos($percentual)),
                'valor' => intdiv($prizePool * Dinheiro::centavos($percentual), 10000),
            ];
        }

        $somaDosPercentuais = array_sum(array_map(fn ($p) => Dinheiro::centavos($p), $percentuais));

        if ($premios && $somaDosPercentuais === 10000) {
            $premios[0]['valor'] += $prizePool - array_sum(array_column($premios, 'valor'));
        }

        return $premios;
    }

    /** @return list<string|int> percentuais na ordem das posições */
    private function percentuais(Jogo $jogo, int $jogadores): array
    {
        if ($jogo->metodo_premiacao === MetodoPremiacao::Manual) {
            return $jogo->premiacoes->pluck('percentual')->all();
        }

        if ($jogadores < 1) {
            return [];
        }

        return RegraPremiacao::query()->paraJogadores($jogadores)->first()?->percentuais ?? [];
    }
}
