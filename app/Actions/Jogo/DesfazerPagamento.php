<?php

namespace App\Actions\Jogo;

use App\Actions\Jogo\Concerns\ChecaRegras;
use App\Enums\DirecaoPagamento;
use App\Enums\StatusJogo;
use App\Enums\TipoAlteracao;
use App\Exceptions\RegraDoJogo;
use App\Models\Jogo;
use App\Models\Pagamento;
use App\Models\Seguranca;
use App\Models\Usuario;
use App\Services\Jogo\Acertos;
use App\Services\Jogo\Historico;
use App\Support\Dinheiro;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Desfaz um pagamento lançado errado, qualquer um (não só o último), também depois de finalizar e no
 * pré-torneio do painel. Com motivo e no histórico. No site pede `jogo.corrigir`; no painel,
 * `pagamentos.registrar`. Nada é apagado: o pagamento fica cancelado.
 *
 * Pagamento de um acerto feito sai pelo DesfazerAcerto (as duas pontas juntas). Depois de finalizar,
 * os acertos em aberto são refeitos com o saldo novo.
 */
class DesfazerPagamento
{
    use ChecaRegras;

    public function __construct(
        private Acertos $acertos,
        private Historico $historico,
    ) {}

    public function __invoke(Pagamento $pagamento, string $motivo, Usuario|Seguranca $quem): void
    {
        $this->permitirNosDois($quem, 'jogo.corrigir', 'pagamentos.registrar', 'Você não tem permissão para desfazer pagamentos.');

        $motivo = Str::limit(trim($motivo), 255, '');

        if ($motivo === '') {
            throw new RegraDoJogo('Diga o motivo para desfazer.');
        }

        DB::transaction(function () use ($pagamento, $motivo, $quem) {
            $jogo = Jogo::query()->lockForUpdate()->findOrFail($pagamento->jogo_id);
            $this->exigirStatus(
                $jogo,
                [StatusJogo::Agendado, StatusJogo::EmAndamento, StatusJogo::Finalizado],
                'Pagamentos só se desfazem com o jogo agendado, em andamento ou finalizado.',
            );

            $pagamento = $jogo->pagamentos()->with('participante.usuario')->findOrFail($pagamento->id);

            if ($pagamento->estaCancelada()) {
                throw new RegraDoJogo('Esse pagamento já foi desfeito.');
            }

            if ($pagamento->acerto_id !== null) {
                throw new RegraDoJogo('Esse pagamento é de um acerto feito: desfaça o acerto no fechamento.');
            }

            $pagamento->cancelar($quem, $motivo);

            if ($jogo->status === StatusJogo::Finalizado) {
                $this->acertos->refazerEmAberto($jogo);
            }

            $nome = $pagamento->participante->nome();
            $verbo = $pagamento->direcao === DirecaoPagamento::JogadorPagou ? 'pagou' : 'recebeu';

            $this->historico->registrar($jogo, $quem, TipoAlteracao::DesfezPagamento,
                "Desfez: {$nome} {$verbo} ".Dinheiro::brl($pagamento->valor).' ('.mb_strtolower($pagamento->forma->label()).')',
                $motivo,
                ['pagamento_id' => $pagamento->id],
            );
        });
    }
}
