<?php

namespace App\Services\Jogo;

use App\Enums\CalculoTaxa;
use App\Enums\MetodoPremiacao;
use App\Enums\TipoItem;
use App\Models\Entrada;
use App\Models\Item;
use App\Models\Jogo;
use App\Models\RegraPremiacao;
use App\Models\Taxa;
use App\Support\ArredondarPremios;
use App\Support\Dinheiro;
use Illuminate\Support\Collection;

/**
 * Arrecadação, premiação bruta, taxas, prize pool e prêmios (docs/regras.md, "Cálculos").
 * Tudo em centavos inteiros. Referência: Poker Night #18 = R$ 2.100 / 210 / 1.890 → 950 / 565 / 375
 * (prêmios arredondados para R$ 5; sem arredondar, 945 / 567 / 378).
 *
 * Um lançamento é o que importa de uma entrada para a conta: tipo, valor em centavos e o percentual
 * que vai para a premiação, em centésimos de ponto (75% = 7500).
 *
 * Torneio sem centavos: a premiação bruta desce para o real inteiro (os centavos ficam fora da
 * premiação, com a casa), a taxa em % arredonda para o real e os prêmios saem em reais (R$ 5 com o
 * "Arredondar prêmios", senão R$ 1), com a sobra para o 1º. Cash game fica como está nas fichas.
 */
class Calculadora
{
    /** @var Collection<int, RegraPremiacao>|null regras do clube, lidas uma vez por instância (relatórios somam muitos jogos) */
    private ?Collection $regras = null;

    /** Números do jogo a partir das entradas válidas (canceladas não contam). */
    public function doJogo(Jogo $jogo): ResumoFinanceiro
    {
        $entradas = $jogo->entradas()->validas()->get(['tipo', 'valor', 'percentual_premiacao', 'participante_id']);

        // Jogador = quem tem pelo menos um buy-in válido.
        $jogadores = $entradas->where('tipo', TipoItem::Buyin)->pluck('participante_id')->unique()->count();

        return $this->calcular(
            $jogo,
            $entradas->map(fn (Entrada $entrada): array => [
                'tipo' => $entrada->tipo,
                'valor' => Dinheiro::centavos($entrada->valor),
                'percentual' => Dinheiro::centavos($entrada->percentual_premiacao),
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
                $lancamentos->push(['tipo' => $item->tipo, 'valor' => Dinheiro::centavos($item->valor), 'percentual' => Dinheiro::centavos($item->percentual_premiacao)]);
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
     * @param  Collection<int, array{tipo: TipoItem, valor: int, percentual: int}>  $lancamentos  percentual em centésimos (75% = 7500)
     */
    public function calcular(Jogo $jogo, Collection $lancamentos, int $jogadores): ResumoFinanceiro
    {
        $arrecadacao = $lancamentos->sum('valor');

        // O percentual vale sobre a soma de quem tem o mesmo percentual (não lançamento a lançamento).
        $premiacaoBruta = $lancamentos->groupBy(fn (array $l): int => $l['percentual'])
            ->sum(fn (Collection $doPercentual, int $percentual): int => intdiv($doPercentual->sum('valor') * $percentual, 10000));

        if ($jogo->eTorneio()) {
            $premiacaoBruta = intdiv($premiacaoBruta, Dinheiro::REAL) * Dinheiro::REAL; // os centavos ficam com a casa
        }
        $entradas = $lancamentos->filter(fn (array $l): bool => $l['tipo']->eEntrada())->count();

        $taxas = $jogo->taxas->map(fn (Taxa $taxa): array => [
            'nome' => $taxa->nome,
            'valor' => $this->taxa($taxa, $premiacaoBruta, $jogadores, $entradas, $jogo->eTorneio()),
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

    private function taxa(Taxa $taxa, int $premiacaoBruta, int $jogadores, int $entradas, bool $emReais): int
    {
        $valor = Dinheiro::centavos($taxa->valor);

        return match ($taxa->calculo) {
            CalculoTaxa::Fixo => $valor,
            // No torneio, a taxa em % arredonda para o real (meio real para cima): 10% de 1.235 = 124.
            CalculoTaxa::Percentual => $emReais
                ? Dinheiro::arredondarParaReal(Dinheiro::percentual($premiacaoBruta, $taxa->valor))
                : Dinheiro::percentual($premiacaoBruta, $taxa->valor),
            CalculoTaxa::PorUnidade => $valor * max(1, (int) $taxa->quantidade),
            CalculoTaxa::PorJogador => $valor * $jogadores,
            CalculoTaxa::PorEntrada => $valor * $entradas,
        };
    }

    /**
     * Prize pool × percentual de cada posição, para baixo; a sobra do arredondamento vai para o 1º.
     * Do 2º para baixo o prêmio desce para o múltiplo de R$ 5 com "Arredondar prêmios para R$ 5"
     * (ArredondarPremios, o padrão), senão para o real, e o 1º leva a diferença:
     * R$ 1.890 em 50/30/20 → 950 / 565 / 375.
     *
     * @return list<array{posicao: int, percentual: string, valor: int}>
     */
    private function premios(Jogo $jogo, int $prizePool, int $jogadores): array
    {
        $percentuais = $this->percentuais($jogo, $jogadores);
        $somaDosPercentuais = array_sum(array_map(fn ($p) => Dinheiro::centavos($p), $percentuais));
        $sobraParaO1o = $somaDosPercentuais === 10000;
        $multiplo = ArredondarPremios::ativo() ? ArredondarPremios::MULTIPLO : Dinheiro::REAL;
        $premios = [];

        foreach ($percentuais as $indice => $percentual) {
            $valor = intdiv($prizePool * Dinheiro::centavos($percentual), 10000);

            // O 1º só não arredonda quando vai receber a sobra (ele fecha a conta do prize pool).
            if ($indice > 0 || ! $sobraParaO1o) {
                $valor = ArredondarPremios::paraBaixo($valor, $multiplo);
            }

            $premios[] = [
                'posicao' => $indice + 1,
                'percentual' => Dinheiro::decimal(Dinheiro::centavos($percentual)),
                'valor' => $valor,
            ];
        }

        if ($premios && $sobraParaO1o) {
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

        // A mesma escolha do RegraPremiacao::scopeParaJogadores: a faixa de maior mínimo que cobre o número.
        $this->regras ??= RegraPremiacao::query()->orderByDesc('jogadores_min')->get();

        return $this->regras->first(fn (RegraPremiacao $regra): bool => $regra->jogadores_min <= $jogadores
            && ($regra->jogadores_max === null || $regra->jogadores_max >= $jogadores))?->percentuais ?? [];
    }
}
