<?php

namespace App\Livewire\Jogo;

use App\Enums\StatusParticipante;
use App\Models\Jogo;
use App\Models\Participante;
use App\Services\Jogo\Calculadora;
use App\Services\Jogo\Entradas;
use App\Services\Jogo\EntradasDoJogador;
use App\Services\Jogo\SaldoDoJogador;
use App\Services\Jogo\Saldos;
use App\Support\EtapaDoJogo;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Tela 10: resumo ao vivo. Números da noite, quanto está pendente com o caixa e a tabela de
 * jogadores (o que cada um comprou, o que pagou e o saldo), filtrável por mesa e por pendência.
 * Clicar num jogador abre o mesmo painel da tela ao vivo (Jogo\AcoesJogador). Atualiza a cada 5 s.
 */
class Resumo extends Component
{
    public Jogo $jogo;

    /** jogo_mesa filtrada (?mesa=); vazio = todas. */
    #[Url(as: 'mesa', except: null)]
    public ?int $mesa = null;

    #[Url(as: 'pendentes', except: false)]
    public bool $soPendentes = false;

    public ?int $selecionado = null;

    /** Jogo finalizado em outro aparelho enquanto a tela estava aberta: vai para a etapa atual. */
    public function hydrate(): void
    {
        if (! EtapaDoJogo::permite($this->jogo, 'resumo')) {
            session()->flash('aviso', "{$this->jogo->nome} não está mais em andamento.");
            $this->redirect(EtapaDoJogo::url($this->jogo) ?? route('jogos.index'));
        }
    }

    public function filtrarMesa(?int $jogoMesaId): void
    {
        $this->mesa = $jogoMesaId === null ? null : $this->jogo->mesas()->findOrFail($jogoMesaId)->id;
    }

    public function selecionar(int $participanteId): void
    {
        $this->selecionado = $this->jogo->participantes()->findOrFail($participanteId)->id;
    }

    #[On('fechar-jogador')]
    public function fecharJogador(): void
    {
        $this->selecionado = null;
    }

    /** O painel do jogador lançou algo: refaz a tabela na hora. */
    #[On('caixa-mudou')]
    public function caixaMudou(): void {}

    public function render(Calculadora $calculadora, Entradas $entradas, Saldos $saldos): View
    {
        $participantes = $this->jogo->participantes()->with(['usuario', 'jogoMesa'])->get();
        $porJogador = $entradas->porJogador($this->jogo);
        $contas = $saldos->doJogo($this->jogo);

        $linhas = $participantes
            ->map(fn (Participante $p) => [
                'participante' => $p,
                'entradas' => $porJogador->get($p->id),
                'conta' => $contas[$p->id],
            ])
            ->sort(fn (array $a, array $b) => [$b['conta']->investido, $a['participante']->nome()] <=> [$a['conta']->investido, $b['participante']->nome()])
            ->values();

        $visiveis = $linhas
            ->when($this->mesa !== null, fn (Collection $l) => $l->filter(fn (array $linha) => $linha['participante']->jogo_mesa_id === $this->mesa))
            ->when($this->soPendentes, fn (Collection $l) => $l->reject(fn (array $linha) => $linha['conta']->quitado()))
            ->values();

        return view('livewire.jogo.resumo', [
            'resumo' => $calculadora->doJogo($this->jogo),
            'mesas' => $this->jogo->mesas()->get(),
            'linhas' => $visiveis,
            'total' => $linhas->count(),
            'contagem' => $this->contagem($porJogador) + [
                'jogando' => $participantes->where('status', StatusParticipante::Jogando)->count(),
            ],
            'pendencias' => $this->pendencias($contas),
            'mostraPremio' => $contas->contains(fn (SaldoDoJogador $c) => $c->premio > 0),
        ]);
    }

    /**
     * @param  Collection<int, EntradasDoJogador>  $porJogador
     * @return array{jogadores: int, rebuys: int, addons: int}
     */
    private function contagem(Collection $porJogador): array
    {
        return [
            'jogadores' => $porJogador->filter(fn (EntradasDoJogador $e) => $e->buyin !== null)->count(),
            'rebuys' => $porJogador->sum('rebuys'),
            'addons' => $porJogador->sum('addons'),
        ];
    }

    /**
     * Quanto os jogadores ainda devem ao caixa, quanto o caixa deve a eles e quanto entrou no caixa.
     *
     * @param  Collection<int, SaldoDoJogador>  $contas
     * @return array{devem: int, aReceber: int, noCaixa: int, quantos: int}
     */
    private function pendencias(Collection $contas): array
    {
        return [
            'devem' => $contas->filter(fn (SaldoDoJogador $c) => $c->saldo > 0)->sum('saldo'),
            'aReceber' => -$contas->filter(fn (SaldoDoJogador $c) => $c->saldo < 0)->sum('saldo'),
            'noCaixa' => $contas->sum('pago') - $contas->sum('recebido'),
            'quantos' => $contas->reject(fn (SaldoDoJogador $c) => $c->quitado())->count(),
        ];
    }
}
