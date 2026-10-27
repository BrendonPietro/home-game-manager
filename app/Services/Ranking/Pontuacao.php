<?php

namespace App\Services\Ranking;

use App\Enums\TipoItem;
use App\Models\Jogo;
use App\Models\Participante;
use App\Models\Ranking;
use App\Models\RankingPonto;
use App\Services\Jogo\Saldos;
use Illuminate\Support\Collection;

/**
 * Pontos de um jogo em cada ranking em que ele vale (docs/regras.md, "Rankings"), gravados ao
 * finalizar em ranking_pontos. Mudar a regra depois não mexe em pontos já dados.
 *
 * Quem jogou = quem tem buy-in. Pontos = os da posição (só as posições listadas no ranking pontuam)
 * + mesa final (se o ranking pontua FT e a posição está até jogos.tamanho_mesa_final)
 * + participação (se o ranking pontua participação). No cash game a posição é a ordem do
 * resultado: maior lucro = 1º.
 */
class Pontuacao
{
    public function __construct(private Saldos $saldos) {}

    /** @return Collection<int, array{usuario_id: int, posicao: ?int, pontos: int, detalhe: array{posicao: int, mesa_final: int, participacao: int}}> */
    public function doJogo(Ranking $ranking, Jogo $jogo): Collection
    {
        $tabela = $ranking->pontuacoes->pluck('pontos', 'posicao');

        return $this->posicoes($jogo)->map(function (array $linha) use ($ranking, $jogo, $tabela) {
            $posicao = $linha['posicao'];
            $detalhe = [
                'posicao' => $posicao ? (int) ($tabela[$posicao] ?? 0) : 0,
                'mesa_final' => $ranking->ft_pontua && $posicao && $posicao <= $jogo->tamanho_mesa_final ? $ranking->pontos_mesa_final : 0,
                'participacao' => $ranking->participacao_pontua ? $ranking->pontos_participacao : 0,
            ];

            return $linha + ['pontos' => array_sum($detalhe), 'detalhe' => $detalhe];
        });
    }

    /** Refaz os pontos do jogo em cada ranking ativo em que ele vale. */
    public function gravar(Jogo $jogo): void
    {
        foreach ($jogo->rankings()->ativos()->with('pontuacoes')->get() as $ranking) {
            RankingPonto::query()->where('ranking_id', $ranking->id)->where('jogo_id', $jogo->id)->delete();

            foreach ($this->doJogo($ranking, $jogo) as $linha) {
                RankingPonto::query()->create(['ranking_id' => $ranking->id, 'jogo_id' => $jogo->id] + $linha);
            }
        }
    }

    /** @return Collection<int, array{usuario_id: int, posicao: ?int}> */
    private function posicoes(Jogo $jogo): Collection
    {
        $jogaram = $jogo->participantes()
            ->whereHas('entradas', fn ($entradas) => $entradas->validas()->where('tipo', TipoItem::Buyin))
            ->orderBy('id')
            ->get();

        if ($jogo->eTorneio()) {
            return $jogaram->map(fn (Participante $p) => ['usuario_id' => $p->usuario_id, 'posicao' => $p->posicao_final]);
        }

        $saldos = $this->saldos->doJogo($jogo);

        return $jogaram->sortByDesc(fn (Participante $p) => $saldos[$p->id]->resultado())
            ->values()
            ->map(fn (Participante $p, int $i) => ['usuario_id' => $p->usuario_id, 'posicao' => $i + 1]);
    }
}
