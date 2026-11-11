<?php

namespace App\Livewire\Jogo;

use App\Actions\Jogo\DesfazerAcao;
use App\Actions\Jogo\DesfazerPagamento;
use App\Actions\Jogo\MarcarPagamentoAntecipado;
use App\Actions\Jogo\RegistrarCashOut;
use App\Actions\Jogo\RegistrarEliminacao;
use App\Actions\Jogo\RegistrarEntrada;
use App\Actions\Jogo\RegistrarPagamento;
use App\Enums\DirecaoPagamento;
use App\Enums\FaseTorneio;
use App\Enums\FormaPagamento;
use App\Enums\StatusParticipante;
use App\Enums\TipoItem;
use App\Exceptions\RegraDoJogo;
use App\Models\Eliminacao;
use App\Models\Entrada;
use App\Models\Item;
use App\Models\Jogo;
use App\Models\Pagamento;
use App\Models\Participante;
use App\Models\Usuario;
use App\Services\Jogo\Saldos;
use App\Support\Dinheiro;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Telas 08, 08b e 09: o painel do jogador na tela ao vivo (abre ao clicar no assento). Mostra o que
 * ele comprou, a conta com o caixa e o histórico; lança entrada, pagamento, eliminação ou cash-out e
 * desfaz a última ação. As regras ficam nas Actions: os botões só refletem o que a fase permite.
 * Cada lançamento avisa a tela ao vivo (evento caixa-mudou) para a mesa e a faixa atualizarem.
 */
class AcoesJogador extends Component
{
    public Jogo $jogo;

    public int $participanteId;

    /** Mudar de lugar só existe na tela ao vivo (é ela que recebe o próximo clique na mesa). */
    public bool $mudaLugar = true;

    /** Modal aberto: entrada, pagamento, eliminacao, cashout ou desfazer. */
    public ?string $modal = null;

    /** Gerado ao abrir o modal: confirmar duas vezes não lança duas vezes. */
    public string $uuid = '';

    public ?string $tipo = null;

    public ?int $itemId = null;

    /** @var list<int|string> opcionais marcados no modal de entrada */
    public array $opcionais = [];

    public string $direcao = 'jogador_pagou';

    public string $valor = '';

    public string $forma = 'pix';

    public string $observacao = '';

    public string $fichas = '';

    public string $motivo = '';

    /** Pagamento escolhido para desfazer depois de finalizar (correção, jogo.corrigir). */
    public ?int $pagamentoId = null;

    public ?string $erro = null;

    public ?string $aviso = null;

    // ── Entrada (buy-in, rebuy, add-on ou opcional por jogador) ──

    public function abrirEntrada(string $tipo, ?int $itemId = null): void
    {
        $tipo = TipoItem::from($tipo);
        $doTipo = $this->itensAtivos()->filter(fn (Item $item) => $item->tipo === $tipo);

        $this->abrir('entrada');
        $this->tipo = $tipo->value;
        $this->itemId = $itemId ?? ($doTipo->firstWhere('padrao', true) ?? $doTipo->first())?->id;
        $this->opcionais = [];
    }

    public function escolher(int $itemId): void
    {
        $this->itemId = $itemId;
    }

    public function confirmarEntrada(): void
    {
        $item = $this->itensAtivos()->firstWhere('id', $this->itemId);

        if (! $item) {
            $this->erro = 'Escolha o que lançar.';

            return;
        }

        $this->executar(function () use ($item) {
            $participante = $this->participante();
            $entrada = app(RegistrarEntrada::class)($participante, $item, $this->quem(), $this->uuid, array_map('intval', $this->opcionais));

            return "{$item->nome} registrado para {$participante->nome()}".RegistrarEntrada::aEntregar($entrada).'.';
        });
    }

    // ── Pagamento antecipado (bônus de fichas) ──

    public function alternarAntecipado(): void
    {
        $this->executar(function () {
            $participante = app(MarcarPagamentoAntecipado::class)($this->participante(), ! $this->participante()->pagou_antes, $this->quem());

            return MarcarPagamentoAntecipado::aviso($participante);
        });
    }

    // ── Pagamento ──

    public function abrirPagamento(): void
    {
        $saldo = app(Saldos::class)->doParticipante($this->participante())->saldo;

        $this->abrir('pagamento');
        $this->direcao = ($saldo < 0 ? DirecaoPagamento::JogadorRecebeu : DirecaoPagamento::JogadorPagou)->value;
        $this->valor = $saldo === 0 ? '' : Dinheiro::paraTexto($saldo);
        $this->forma = FormaPagamento::Pix->value;
        $this->observacao = '';
    }

    public function confirmarPagamento(): void
    {
        $centavos = Dinheiro::lerTexto($this->valor);

        if ($centavos === null) {
            $this->erro = 'Valor inválido: digite, por exemplo, 100 ou 100,50.';

            return;
        }

        $direcao = DirecaoPagamento::tryFrom($this->direcao) ?? DirecaoPagamento::JogadorPagou;
        $forma = FormaPagamento::tryFrom($this->forma) ?? FormaPagamento::Pix;

        $this->executar(function () use ($centavos, $direcao, $forma) {
            $participante = $this->participante();
            app(RegistrarPagamento::class)($participante, $direcao, $centavos, $forma, $this->quem(), $this->uuid, $this->observacao);

            return $participante->nome().($direcao === DirecaoPagamento::JogadorPagou ? ' pagou ' : ' recebeu ')
                .Dinheiro::brlCentavos($centavos).' ('.mb_strtolower($forma->label()).').';
        });
    }

    // ── Eliminação (torneio) e cash-out (cash game) ──

    public function abrirEliminacao(): void
    {
        $this->abrir('eliminacao');
    }

    public function confirmarEliminacao(): void
    {
        $this->executar(function () {
            $participante = $this->participante();
            $eliminacao = app(RegistrarEliminacao::class)($participante, $this->quem());
            $posicao = $participante->refresh()->posicao_final;

            return match (true) {
                ! $eliminacao->definitiva => "{$participante->nome()} eliminado: pode voltar com rebuy até fechar as entradas.",
                $posicao !== null => "{$participante->nome()} eliminado em {$posicao}º.",
                default => "{$participante->nome()} eliminado.",
            };
        });
    }

    public function abrirCashOut(): void
    {
        $this->abrir('cashout');
        $this->fichas = (string) ($this->participante()->fichas_finais ?? '');
    }

    public function confirmarCashOut(): void
    {
        $fichas = $this->fichasDigitadas();

        if ($fichas === null) {
            $this->erro = 'Informe as fichas finais, só com números.';

            return;
        }

        $this->executar(function () use ($fichas) {
            $participante = app(RegistrarCashOut::class)($this->participante(), $fichas, $this->quem());

            return "Cash-out de {$participante->nome()}: ".Dinheiro::brl($participante->cash_out).'.';
        });
    }

    // ── Desfazer a última ação ──

    public function abrirDesfazer(): void
    {
        $this->abrir('desfazer');
        $this->motivo = '';
    }

    public function confirmarDesfazer(): void
    {
        $this->executar(fn () => app(DesfazerAcao::class)($this->participante(), $this->motivo, $this->quem()));
    }

    // ── Correção depois de finalizar: desfazer um pagamento lançado errado (jogo.corrigir) ──

    public function abrirDesfazerPagamento(int $pagamentoId): void
    {
        $this->pagamentoId = $this->participante()->pagamentos()->validas()->findOrFail($pagamentoId)->id;
        $this->abrir('desfazerPagamento');
        $this->motivo = '';
    }

    public function confirmarDesfazerPagamento(): void
    {
        $this->executar(function () {
            $pagamento = $this->participante()->pagamentos()->findOrFail($this->pagamentoId);
            app(DesfazerPagamento::class)($pagamento, $this->motivo, $this->quem());

            return 'Pagamento desfeito: '.$this->descrever($pagamento).'.';
        });
    }

    public function fecharModal(): void
    {
        $this->modal = null;
        $this->erro = null;
    }

    private function abrir(string $modal): void
    {
        $this->modal = $modal;
        $this->uuid = (string) Str::uuid();
        $this->erro = null;
        $this->aviso = null;
    }

    /**
     * Roda uma Action (que devolve a mensagem de sucesso). Regra ou permissão aparece no modal,
     * sem derrubar a tela; deu certo, fecha o modal e avisa a tela ao vivo.
     */
    private function executar(callable $acao): void
    {
        $this->erro = null;

        try {
            $this->aviso = $acao();
        } catch (RegraDoJogo|AuthorizationException $erro) {
            $this->erro = $erro->getMessage();

            return;
        }

        $this->modal = null;
        $this->dispatch('caixa-mudou');
    }

    private function participante(): Participante
    {
        return $this->jogo->participantes()->with(['usuario', 'jogoMesa'])->findOrFail($this->participanteId);
    }

    /** @return Collection<int, Item> */
    private function itensAtivos(): Collection
    {
        return $this->jogo->itens()->ativos()->get();
    }

    private function fichasDigitadas(): ?int
    {
        $fichas = preg_replace('/[.\s]/', '', $this->fichas);

        return $fichas !== '' && ctype_digit($fichas) ? (int) $fichas : null;
    }

    private function quem(): Usuario
    {
        return auth('web')->user();
    }

    public function render(Saldos $saldos, DesfazerAcao $desfazer): View
    {
        $participante = $this->participante();
        // Os opcionais válidos de cada entrada já vêm junto: o histórico não consulta linha por linha.
        $entradas = $participante->entradas()->validas()->with(['item', 'opcionais' => fn ($q) => $q->validas()])->orderBy('id')->get();
        $itens = $this->itensAtivos();
        $temBuyin = $entradas->contains(fn (Entrada $e) => $e->tipo === TipoItem::Buyin);

        return view('livewire.jogo.acoes-jogador', [
            'participante' => $participante,
            'nome' => $participante->nome(),
            'entradas' => $entradas,
            'temBuyin' => $temBuyin,
            'itens' => $itens,
            'saldo' => $saldos->doParticipante($participante),
            'historico' => $this->historico($participante, $entradas),
            'ultima' => $desfazer->ultimaAcao($participante),
            'botoes' => $this->botoes($participante, $entradas, $itens, $temBuyin),
            'avulsos' => $this->opcionaisAvulsos($participante, $entradas, $itens),
            'previsaoPosicao' => $this->modal === 'eliminacao' ? $this->previsaoPosicao() : null,
            'podeSerEliminado' => $this->jogo->eTorneio() && $participante->status === StatusParticipante::Jogando && $temBuyin
                && $this->jogo->participantes()->naMesa()
                    ->whereHas('entradas', fn ($q) => $q->validas()->where('tipo', TipoItem::Buyin))
                    ->count() > 1,
            'fichasDigitadas' => $this->modal === 'cashout' ? $this->fichasDigitadas() : null,
        ]);
    }

    /**
     * Entradas (com os opcionais junto), pagamentos e eliminações, do mais recente para o mais antigo.
     * No mesmo segundo, a mesma ordem do DesfazerAcao: entrada, eliminação, pagamento.
     *
     * @param  Collection<int, Entrada>  $entradas
     * @return Collection<int, Entrada|Pagamento|Eliminacao>
     */
    private function historico(Participante $participante, Collection $entradas): Collection
    {
        $ordem = fn ($acao): int => match (true) {
            $acao instanceof Pagamento => 2,
            $acao instanceof Eliminacao => 1,
            default => 0,
        };

        return $entradas->whereNull('vinculada_a')
            ->concat($participante->eliminacoes()->validas()->get())
            ->concat($participante->pagamentos()->validas()->get())
            ->sort(fn ($a, $b) => [$b->created_at, $ordem($b), $b->id] <=> [$a->created_at, $ordem($a), $a->id])
            ->values();
    }

    /**
     * O que cada botão grande mostra: se dá para clicar agora, a linha de baixo e, no VOLTAR, o rótulo.
     * Só reflete a fase e o que o jogador já tem; quem decide é o RegistrarEntrada.
     *
     * Quem caiu com as entradas abertas volta pelo botão do buy-in, que vira VOLTAR e lança um rebuy
     * (buy-in é um por jogador): na hora ou depois, no intervalo.
     *
     * @param  Collection<int, Entrada>  $entradas
     * @param  Collection<int, Item>  $itens
     * @return array<string, array{tipo: TipoItem, ok: bool, meta: string, rotulo?: string}>
     */
    private function botoes(Participante $participante, Collection $entradas, Collection $itens, bool $temBuyin): array
    {
        $abertas = $this->jogo->entradasAbertas();
        $saiu = $participante->status === StatusParticipante::Saiu;
        $eliminado = $participante->status === StatusParticipante::Eliminado;
        $fase = $this->jogo->fase;
        $menor = fn (TipoItem $tipo): ?int => $itens->filter(fn (Item $i) => $i->tipo === $tipo)
            ->map(fn (Item $i) => Dinheiro::centavos($i->valor))->min();
        $preco = function (TipoItem $tipo) use ($itens, $menor): string {
            $varios = $itens->filter(fn (Item $i) => $i->tipo === $tipo)->count() > 1;

            return ($varios ? 'a partir de ' : '').Dinheiro::brlCentavos($menor($tipo));
        };

        $botoes = [];

        if ($menor(TipoItem::Buyin) !== null) {
            $botoes['buyin'] = [
                'tipo' => TipoItem::Buyin,
                'ok' => $abertas && ! $temBuyin && ! $saiu && ! $eliminado,
                'meta' => match (true) {
                    $temBuyin => 'já feito',
                    ! $abertas => 'entradas fechadas',
                    default => $preco(TipoItem::Buyin),
                },
            ];
        }

        $rebuyAberto = $this->jogo->eCashGame() || in_array($fase, [FaseTorneio::Rebuys, FaseTorneio::Intervalo], true);

        if ($eliminado && $abertas && $rebuyAberto && $temBuyin && $menor(TipoItem::Rebuy) !== null) {
            $botoes['buyin'] = [
                'tipo' => TipoItem::Rebuy,
                'ok' => true,
                'meta' => 'entra como rebuy · '.$preco(TipoItem::Rebuy),
                'rotulo' => 'Voltar',
            ];
        } elseif ($menor(TipoItem::Rebuy) !== null) {
            $botoes['rebuy'] = [
                'tipo' => TipoItem::Rebuy,
                'ok' => $abertas && $rebuyAberto && $temBuyin && ! $saiu,
                'meta' => match (true) {
                    ! $abertas || ! $rebuyAberto => 'encerrado',
                    ! $temBuyin => 'falta o buy-in',
                    default => $preco(TipoItem::Rebuy),
                },
            ];
        }

        if ($this->jogo->eTorneio() && $menor(TipoItem::Addon) !== null) {
            $addons = $itens->filter(fn (Item $i) => $i->tipo === TipoItem::Addon);
            $todosNoLimite = $addons->every(fn (Item $i) => $i->limite_por_jogador
                && $entradas->where('jogo_item_id', $i->id)->count() >= $i->limite_por_jogador);
            $botoes['addon'] = [
                'tipo' => TipoItem::Addon,
                'ok' => $fase === FaseTorneio::Intervalo && $temBuyin && ! $eliminado && ! $todosNoLimite,
                'meta' => match (true) {
                    $todosNoLimite => 'já feito',
                    $fase === FaseTorneio::Rebuys => 'no intervalo',
                    $fase !== FaseTorneio::Intervalo => 'encerrado',
                    ! $temBuyin => 'falta o buy-in',
                    default => $preco(TipoItem::Addon),
                },
            ];
        }

        return $botoes;
    }

    /**
     * Opcionais "1 por jogador" que ele ainda não comprou (ex.: churrasco), vendidos sozinhos.
     *
     * @param  Collection<int, Entrada>  $entradas
     * @param  Collection<int, Item>  $itens
     * @return Collection<int, Item>
     */
    private function opcionaisAvulsos(Participante $participante, Collection $entradas, Collection $itens): Collection
    {
        if (! $this->jogo->entradasAbertas() || $participante->estaFora()) {
            return collect();
        }

        return $itens->filter(fn (Item $i) => $i->tipo === TipoItem::Opcional && $i->porJogador()
            && ! $entradas->contains('jogo_item_id', $i->id))->values();
    }

    /** Com as entradas fechadas, a posição de quem cair agora (último entre os que restam). */
    private function previsaoPosicao(): ?int
    {
        if ($this->jogo->fase !== FaseTorneio::SemEntradas) {
            return null;
        }

        $comBuyin = $this->jogo->entradas()->validas()->where('tipo', TipoItem::Buyin)->distinct()->count('participante_id');

        return $comBuyin - $this->jogo->eliminacoes()->validas()->definitivas()->count();
    }

    /** Linha do histórico e do desfazer: "Rebuy · R$ 100", "Pagou R$ 50 (pix)", "Eliminação · 7º". */
    public function descrever(Entrada|Pagamento|Eliminacao $acao): string
    {
        return match (true) {
            $acao instanceof Entrada => $acao->item->nome.' · '.Dinheiro::brlCentavos(
                Dinheiro::centavos($acao->valor) + ($acao->relationLoaded('opcionais') ? $acao->opcionais : $acao->opcionais()->validas()->get())
                    ->sum(fn (Entrada $o) => Dinheiro::centavos($o->valor))
            ),
            $acao instanceof Pagamento => ($acao->direcao === DirecaoPagamento::JogadorPagou ? 'Pagou ' : 'Recebeu ')
                .Dinheiro::brl($acao->valor).' ('.mb_strtolower($acao->forma->label()).')',
            $acao instanceof Eliminacao => 'Eliminação'.($acao->definitiva ? '' : ' provisória'),
        };
    }
}
