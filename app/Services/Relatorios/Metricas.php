<?php

namespace App\Services\Relatorios;

use App\Enums\StatusJogo;
use App\Enums\TipoItem;
use App\Models\Entrada;
use App\Models\Item;
use App\Models\Jogo;
use App\Services\Jogo\Calculadora;
use App\Support\Dinheiro;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Métricas do painel (Dashboards e relatórios → Métricas), dos jogos finalizados no período: médias de
 * jogadores, prize pool, arrecadação e de quanto cada jogador gasta por noite, rebuys e add-ons por
 * jogador, e a mesma conta por tipo de evento (o modelo de onde o jogo saiu), por modalidade (torneio ×
 * cash game), pelo valor do buy-in e mês a mês. Cada grupo diz quanto o gasto por jogador fica acima ou
 * abaixo da média geral.
 *
 * Tudo em centavos, com as médias em dinheiro arredondadas para o real. "Gasto por jogador" = arrecadação ÷
 * jogadores (pesa cada noite pelo tamanho dela).
 * No cash game o "prize pool" é o que está nas fichas (premiação bruta − taxas).
 */
class Metricas
{
    public function __construct(private Calculadora $calculadora) {}

    /**
     * @return array{
     *     periodo: string,
     *     geral: array<string, int|float>,
     *     porModelo: list<array<string, mixed>>,
     *     porModalidade: list<array<string, mixed>>,
     *     porBuyin: list<array<string, mixed>>,
     *     porMes: list<array<string, mixed>>,
     * }
     */
    public function gerar(Filtro $filtro): array
    {
        $noites = $this->noites($filtro);
        $geral = $this->agregar($noites);
        $comparar = fn (Collection $grupos): array => $grupos
            ->map(fn (Collection $doGrupo, string $grupo): array => ['grupo' => $grupo] + $this->agregar($doGrupo)
                + ['diferenca' => $this->diferenca($this->agregar($doGrupo)['gastoPorJogador'], $geral['gastoPorJogador'])])
            ->sortByDesc('gastoPorJogador')
            ->values()
            ->all();

        return [
            'periodo' => $filtro->periodo,
            'geral' => $geral,
            'porModelo' => $comparar($noites->groupBy('modelo')),
            'porModalidade' => $comparar($noites->groupBy('modalidade')),
            'porBuyin' => collect($comparar($noites->groupBy('buyinTexto')))
                ->sortBy(fn (array $linha): int => $noites->firstWhere('buyinTexto', $linha['grupo'])['buyin'])
                ->values()
                ->all(),
            'porMes' => $noites->groupBy('mes')
                ->map(fn (Collection $doMes, string $mes): array => [
                    'mes' => $mes,
                    'rotulo' => Str::ucfirst(CarbonImmutable::createFromFormat('Y-m', $mes)->translatedFormat('M/y')),
                ] + $this->agregar($doMes))
                ->sortKeys()
                ->values()
                ->all(),
        ];
    }

    /**
     * Uma linha por jogo finalizado no período, com os números que as médias usam.
     *
     * @return Collection<int, array{jogo: Jogo, modelo: string, modalidade: string, buyin: int, buyinTexto: string, mes: string,
     *     jogadores: int, arrecadacao: int, prizePool: int, rebuys: int, addons: int}>
     */
    private function noites(Filtro $filtro): Collection
    {
        $jogos = Jogo::query()->reais()->comStatus(StatusJogo::Finalizado)
            ->when($filtro->de, fn (Builder $q, CarbonImmutable $de) => $q->whereDate('data', '>=', $de))
            ->when($filtro->ate, fn (Builder $q, CarbonImmutable $ate) => $q->whereDate('data', '<=', $ate))
            ->with(['taxas', 'premiacoes', 'modelo', 'itens'])
            ->orderBy('data')
            ->get();

        // Rebuys e add-ons de todos os jogos numa consulta só.
        $contagens = Entrada::query()->validas()
            ->whereIn('jogo_id', $jogos->modelKeys())
            ->whereIn('tipo', [TipoItem::Rebuy, TipoItem::Addon])
            ->selectRaw('jogo_id, tipo, count(*) as quantas')
            ->groupBy('jogo_id', 'tipo')
            ->get()
            ->groupBy('jogo_id');

        return $jogos->map(function (Jogo $jogo) use ($contagens): array {
            $resumo = $this->calculadora->doJogo($jogo);
            $doJogo = $contagens->get($jogo->id, collect());
            $quantas = fn (TipoItem $tipo): int => (int) ($doJogo->first(fn ($c) => $c->tipo === $tipo)?->quantas ?? 0);
            $buyins = $jogo->itens->where('tipo', TipoItem::Buyin);
            $buyin = Dinheiro::centavos(($buyins->first(fn (Item $i) => $i->padrao) ?? $buyins->first())?->valor);

            return [
                'jogo' => $jogo,
                'modelo' => $jogo->modelo?->nome_modelo ?? 'Sem modelo',
                'modalidade' => $jogo->modalidade->label(),
                'buyin' => $buyin,
                'buyinTexto' => 'Buy-in '.Dinheiro::brlCentavos($buyin),
                'mes' => $jogo->data->format('Y-m'),
                'jogadores' => $resumo->jogadores,
                'arrecadacao' => $resumo->arrecadacao,
                'prizePool' => $resumo->prizePool,
                'rebuys' => $quantas(TipoItem::Rebuy),
                'addons' => $quantas(TipoItem::Addon),
            ];
        });
    }

    /**
     * Médias de um conjunto de noites (centavos para dinheiro, uma casa para as contagens).
     *
     * @param  Collection<int, array<string, mixed>>  $noites
     * @return array{jogos: int, jogadores: int, mediaJogadores: float, mediaPrizePool: int, mediaArrecadacao: int,
     *     gastoPorJogador: int, rebuysPorJogador: float, addonsPorJogador: float, arrecadacao: int}
     */
    private function agregar(Collection $noites): array
    {
        $jogos = $noites->count();
        $jogadores = $noites->sum('jogadores');
        $arrecadacao = $noites->sum('arrecadacao');
        // Média em dinheiro sai em reais inteiros (meio real para cima), como o resto do torneio.
        $media = fn (int $total, int $por): int => $por > 0 ? Dinheiro::arredondarParaReal(intdiv($total + intdiv($por, 2), $por)) : 0;
        $porJogador = fn (int $total): float => $jogadores > 0 ? round($total / $jogadores, 2) : 0.0;

        return [
            'jogos' => $jogos,
            'jogadores' => $jogadores,
            'mediaJogadores' => $jogos > 0 ? round($jogadores / $jogos, 1) : 0.0,
            'mediaPrizePool' => $media($noites->sum('prizePool'), $jogos),
            'mediaArrecadacao' => $media($arrecadacao, $jogos),
            'gastoPorJogador' => $media($arrecadacao, $jogadores),
            'rebuysPorJogador' => $porJogador($noites->sum('rebuys')),
            'addonsPorJogador' => $porJogador($noites->sum('addons')),
            'arrecadacao' => $arrecadacao,
        ];
    }

    /** Quanto o valor fica acima (+) ou abaixo (−) da referência, em % inteiro. */
    private function diferenca(int $valor, int $referencia): int
    {
        return $referencia > 0 ? (int) round(($valor - $referencia) * 100 / $referencia) : 0;
    }
}
