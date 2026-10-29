<?php

namespace App\Livewire\Jogo;

use App\Enums\FaseTorneio;
use App\Enums\StatusJogo;
use App\Enums\StatusParticipante;
use App\Models\Jogo;
use App\Models\Participante;
use App\Services\Jogo\Calculadora;
use App\Services\Jogo\Relogio;
use App\Services\Jogo\ResumoFinanceiro;
use App\Support\Dinheiro;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Component;

/**
 * v2: tela cheia para a TV (rota jogos.tv), só para ver: relógio grande, nível, blinds e ante, o que
 * vem depois, prize pool, premiação, jogando, entradas e stack médio. Atualiza a cada 5 s (também com
 * a aba em segundo plano); o relógio conta no navegador e o som da troca de nível e a tela cheia são
 * do Alpine (resources/js/site/tv.js). Antes de começar mostra quando começa; depois de finalizar, quem
 * ficou com os prêmios.
 */
class Tv extends Component
{
    public Jogo $jogo;

    public function render(Calculadora $calculadora, Relogio $relogio): View
    {
        $this->jogo = $relogio->sincronizar($this->jogo); // o nível segue o relógio, como na tela ao vivo

        $participantes = $this->jogo->participantes()->with('usuario')->get();
        $jogando = $participantes->where('status', StatusParticipante::Jogando)->count();
        $fichas = (int) $this->jogo->entradas()->validas()->sum('fichas');
        $resumo = $calculadora->doJogo($this->jogo);

        return view('livewire.jogo.tv', [
            'relogio' => $relogio->estado($this->jogo),
            'resumo' => $resumo,
            'jogando' => $jogando,
            'confirmados' => $participantes->whereIn('status', [StatusParticipante::Confirmado, StatusParticipante::Jogando])->count(),
            'stackMedio' => $this->jogo->eTorneio() && $jogando > 0 && $fichas > 0 ? (int) round($fichas / $jogando) : null,
            'premiosGravados' => $this->premiosGravados(),
            'premiacao' => $this->premiacao($resumo, $participantes),
        ]);
    }

    /** Entradas fechadas (ou jogo finalizado): os prêmios já estão gravados e não mudam mais. */
    private function premiosGravados(): bool
    {
        return $this->jogo->eTorneio()
            && ($this->jogo->fase === FaseTorneio::SemEntradas || $this->jogo->status === StatusJogo::Finalizado);
    }

    /**
     * Prêmio de cada posição: o gravado (com quem já está nela) ou, antes de fechar as entradas, a conta
     * de agora pela Calculadora (cresce com os rebuys e add-ons).
     *
     * @param  Collection<int, Participante>  $participantes
     * @return list<array{posicao: int, valor: int, quem: ?string}> valor em centavos
     */
    private function premiacao(ResumoFinanceiro $resumo, Collection $participantes): array
    {
        if (! $this->jogo->eTorneio()) {
            return [];
        }

        if ($this->premiosGravados()) {
            return $this->jogo->premiacoes()->get()->map(fn ($premio): array => [
                'posicao' => $premio->posicao,
                'valor' => Dinheiro::centavos($premio->valor),
                'quem' => $participantes->firstWhere('posicao_final', $premio->posicao)?->nome(),
            ])->all();
        }

        return array_map(fn (array $premio): array => [
            'posicao' => $premio['posicao'],
            'valor' => $premio['valor'],
            'quem' => null,
        ], $resumo->premios);
    }
}
