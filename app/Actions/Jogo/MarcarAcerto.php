<?php

namespace App\Actions\Jogo;

use App\Actions\Jogo\Concerns\ChecaRegras;
use App\Enums\DirecaoPagamento;
use App\Enums\FormaPagamento;
use App\Enums\StatusJogo;
use App\Enums\TipoAlteracao;
use App\Exceptions\RegraDoJogo;
use App\Models\Acerto;
use App\Models\Jogo;
use App\Models\Participante;
use App\Models\Usuario;
use App\Services\Jogo\Acertos;
use App\Services\Jogo\Historico;
use App\Services\Jogo\Saldos;
use App\Support\Dinheiro;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Um acerto do fechamento foi feito (ex.: Marcão pagou João por pix). Vira pagamentos para os saldos
 * fecharem: quem pagou fica com "jogador pagou" e quem recebeu com "jogador recebeu"; o caixa (lado
 * nulo) não tem linha, e o dinheiro dele muda só quando ele é uma das pontas. Só com o jogo finalizado.
 *
 * O acerto tem de bater com os saldos de agora (quem paga ainda deve o valor e quem recebe ainda tem
 * o valor a receber). Se alguém acertou por fora (pagamento pelo painel do jogador), os acertos em
 * aberto são refeitos e nada é lançado: o mesmo dinheiro nunca entra duas vezes.
 */
class MarcarAcerto
{
    use ChecaRegras;

    public const MUDOU = 'Os acertos mudaram com um pagamento lançado agora: confira a lista de novo.';

    public function __construct(
        private Saldos $saldos,
        private Acertos $acertos,
        private Historico $historico,
    ) {}

    public function __invoke(Acerto $acerto, FormaPagamento $forma, Usuario $quem): Acerto
    {
        $this->permitir($quem, 'pagamentos.registrar', 'Você não tem permissão para registrar pagamentos.');

        if (! $forma->disponivel()) {
            throw new RegraDoJogo('Forma de pagamento não aceita: use Pix ou Dinheiro.');
        }

        $feito = DB::transaction(function () use ($acerto, $forma, $quem): ?Acerto {
            $jogo = Jogo::query()->lockForUpdate()->findOrFail($acerto->jogo_id);
            $this->exigirStatus($jogo, [StatusJogo::Finalizado], 'Os acertos se marcam depois de finalizar.');

            $acerto = $jogo->acertos()->with(['de.usuario', 'para.usuario'])->find($acerto->id)
                ?? throw new RegraDoJogo(self::MUDOU);

            if ($acerto->pago) {
                throw new RegraDoJogo('Esse acerto já foi marcado como feito.');
            }

            if (! $this->bateComOsSaldos($acerto)) {
                $this->acertos->refazerEmAberto($jogo);

                return null; // grava a lista nova e avisa (fora da transação)
            }

            $nome = fn (?Participante $p): string => $p?->nome() ?? 'o caixa';
            $lancar = fn (Participante $participante, DirecaoPagamento $direcao, string $observacao) => $jogo->pagamentos()->create([
                'uuid' => (string) Str::uuid(),
                'participante_id' => $participante->id,
                'acerto_id' => $acerto->id,
                'direcao' => $direcao,
                'valor' => $acerto->valor,
                'forma' => $forma,
                'observacao' => $observacao,
                'registrado_por' => $quem->id,
            ]);

            if ($acerto->de) {
                $lancar($acerto->de, DirecaoPagamento::JogadorPagou, 'Acerto: pagou a '.$nome($acerto->para));
            }

            if ($acerto->para) {
                $lancar($acerto->para, DirecaoPagamento::JogadorRecebeu, 'Acerto: recebeu de '.$nome($acerto->de));
            }

            $acerto->forceFill(['pago' => true, 'pago_em' => now()])->save();

            $this->historico->registrar($jogo, $quem, TipoAlteracao::AcertoFeito,
                'Acerto feito: '.$nome($acerto->de).' → '.$nome($acerto->para).' '.Dinheiro::brl($acerto->valor).' ('.mb_strtolower($forma->label()).')',
            );

            return $acerto;
        });

        return $feito ?? throw new RegraDoJogo(self::MUDOU);
    }

    /** Quem paga ainda deve pelo menos o valor e quem recebe ainda tem pelo menos o valor a receber. */
    private function bateComOsSaldos(Acerto $acerto): bool
    {
        $valor = Dinheiro::centavos($acerto->valor);

        return (! $acerto->de || $this->saldos->doParticipante($acerto->de)->saldo >= $valor)
            && (! $acerto->para || $this->saldos->doParticipante($acerto->para)->saldo <= -$valor);
    }
}
