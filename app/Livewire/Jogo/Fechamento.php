<?php

namespace App\Livewire\Jogo;

use App\Actions\Jogo\FinalizarJogo;
use App\Actions\Jogo\MarcarAcerto;
use App\Enums\FormaPagamento;
use App\Enums\TipoItem;
use App\Exceptions\RegraDoJogo;
use App\Models\Acerto;
use App\Models\Jogo;
use App\Models\Participante;
use App\Models\Usuario;
use App\Services\Jogo\Acertos;
use App\Services\Jogo\Calculadora;
use App\Services\Jogo\SaldoDoJogador;
use App\Services\Jogo\Saldos;
use App\Services\Jogo\Transferencia;
use App\Support\Dinheiro;
use App\Support\EtapaDoJogo;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Tela 14: fechamento financeiro. Números da noite e a conferência, o resultado e o saldo de cada um,
 * e os acertos (quem paga quem). Antes de finalizar os acertos são a sugestão do momento; o Finalizar
 * grava a sugestão, e daí cada acerto feito se marca aqui (vira pagamentos). Saldo em aberto vira
 * pendência: clicar no jogador abre o painel para registrar o pagamento.
 */
class Fechamento extends Component
{
    public Jogo $jogo;

    public ?int $selecionado = null;

    /** Modal de confirmação do Finalizar. */
    public bool $confirmando = false;

    /** Forma do pagamento ao marcar um acerto como feito. */
    public string $forma = 'pix';

    public ?string $erro = null;

    public ?string $aviso = null;

    public function hydrate(): void
    {
        if (! EtapaDoJogo::permite($this->jogo, 'fechamento')) {
            session()->flash('aviso', 'O fechamento não abre agora.');
            $this->redirect(EtapaDoJogo::url($this->jogo) ?? route('jogos.index'));
        }
    }

    public function abrirFinalizar(): void
    {
        $this->confirmando = true;
        $this->erro = null;
    }

    public function fecharFinalizar(): void
    {
        $this->confirmando = false;
        $this->erro = null;
    }

    public function finalizar(): void
    {
        if ($this->executar(fn () => app(FinalizarJogo::class)($this->jogo, $this->quem()))) {
            // Recarrega a página: a topbar e o fluxo da noite (fora do Livewire) passam a mostrar o jogo finalizado.
            session()->flash('aviso', ($this->jogo->eTorneio() ? 'Torneio' : 'Jogo').' finalizado: os acertos abaixo estão gravados.');
            $this->redirect(route('jogos.fechamento', $this->jogo));
        }
    }

    public function marcarAcerto(int $acertoId): void
    {
        $acerto = $this->jogo->acertos()->findOrFail($acertoId);
        $forma = FormaPagamento::tryFrom($this->forma) ?? FormaPagamento::Pix;

        if ($this->executar(fn () => app(MarcarAcerto::class)($acerto, $forma, $this->quem()))) {
            $this->aviso = 'Acerto marcado como feito.';
        }
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

    #[On('caixa-mudou')]
    public function caixaMudou(): void {}

    private function executar(callable $acao): bool
    {
        $this->erro = null;
        $this->aviso = null;

        try {
            $acao();
        } catch (RegraDoJogo|AuthorizationException $erro) {
            $this->erro = $erro->getMessage();

            return false;
        }

        $this->jogo->refresh();

        return true;
    }

    private function quem(): Usuario
    {
        return auth('web')->user();
    }

    public function render(Calculadora $calculadora, Saldos $saldos, Acertos $acertos): View
    {
        $resumo = $calculadora->doJogo($this->jogo);
        $contas = $saldos->doJogo($this->jogo);
        $participantes = $this->jogo->participantes()->with('usuario')->get()->keyBy('id');
        $jogaram = $participantes->filter(fn (Participante $p) => $contas[$p->id]->investido > 0 || $contas[$p->id]->pago > 0 || $contas[$p->id]->recebido > 0);
        $premios = $contas->sum(fn (SaldoDoJogador $c) => $c->premio);
        $retido = $resumo->totalTaxas + $resumo->foraDaPremiacao;

        return view('livewire.jogo.fechamento', [
            'resumo' => $resumo,
            'premios' => $premios,
            'retido' => $retido,
            // Arrecadado tem de ser igual a prêmios (ou cash-outs) + o que a casa retém.
            'diferenca' => $resumo->arrecadacao - $premios - $retido,
            'linhas' => $jogaram
                ->sortByDesc(fn (Participante $p) => [$contas[$p->id]->resultado(), -$p->id])
                ->map(fn (Participante $p) => ['participante' => $p, 'conta' => $contas[$p->id]])
                ->values(),
            'acertos' => $this->acertos($acertos, $participantes),
            'faltam' => $this->faltam($participantes),
        ]);
    }

    /**
     * Gravados (jogo finalizado) ou a sugestão do momento.
     *
     * @param  Collection<int, Participante>  $participantes
     * @return Collection<int, array{id: ?int, de: ?Participante, para: ?Participante, valor: int, pago: bool}>
     */
    private function acertos(Acertos $acertos, Collection $participantes): Collection
    {
        if ($this->jogo->estaFinalizado()) {
            return $this->jogo->acertos()->orderBy('id')->get()->map(fn (Acerto $a) => [
                'id' => $a->id,
                'de' => $participantes->get($a->de_participante_id),
                'para' => $participantes->get($a->para_participante_id),
                'valor' => Dinheiro::centavos($a->valor),
                'pago' => $a->pago,
            ]);
        }

        return collect($acertos->sugerir($this->jogo))->map(fn (Transferencia $t) => [
            'id' => null,
            'de' => $participantes->get($t->de),
            'para' => $participantes->get($t->para),
            'valor' => $t->valor,
            'pago' => false,
        ]);
    }

    /**
     * Quem ainda impede o Finalizar: sem posição (torneio) ou sem cash-out (cash game).
     *
     * @param  Collection<int, Participante>  $participantes
     * @return Collection<int, string>
     */
    private function faltam(Collection $participantes): Collection
    {
        if ($this->jogo->estaFinalizado()) {
            return collect();
        }

        $comBuyin = $this->jogo->entradas()->validas()->where('tipo', TipoItem::Buyin)->pluck('participante_id');

        return $participantes->only($comBuyin->all())
            ->filter(fn (Participante $p) => $this->jogo->eTorneio() ? $p->posicao_final === null : $p->cash_out === null)
            ->map->nome()
            ->values();
    }
}
