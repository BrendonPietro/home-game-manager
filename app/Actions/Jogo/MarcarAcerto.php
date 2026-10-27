<?php

namespace App\Actions\Jogo;

use App\Actions\Jogo\Concerns\ChecaRegras;
use App\Enums\DirecaoPagamento;
use App\Enums\FormaPagamento;
use App\Enums\StatusJogo;
use App\Exceptions\RegraDoJogo;
use App\Models\Acerto;
use App\Models\Jogo;
use App\Models\Participante;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Um acerto do fechamento foi feito (ex.: Marcão pagou João por pix). Vira pagamentos para os saldos
 * fecharem: quem pagou fica com "jogador pagou" e quem recebeu com "jogador recebeu"; o caixa (lado
 * nulo) não tem linha, e o dinheiro dele muda só quando ele é uma das pontas. Só com o jogo finalizado.
 */
class MarcarAcerto
{
    use ChecaRegras;

    public function __invoke(Acerto $acerto, FormaPagamento $forma, Usuario $quem): Acerto
    {
        $this->permitir($quem, 'pagamentos.registrar', 'Você não tem permissão para registrar pagamentos.');

        return DB::transaction(function () use ($acerto, $forma, $quem) {
            $jogo = Jogo::query()->lockForUpdate()->findOrFail($acerto->jogo_id);
            $this->exigirStatus($jogo, [StatusJogo::Finalizado], 'Os acertos se marcam depois de finalizar.');

            $acerto = $jogo->acertos()->with(['de.usuario', 'para.usuario'])->findOrFail($acerto->id);

            if ($acerto->pago) {
                throw new RegraDoJogo('Esse acerto já foi marcado como feito.');
            }

            $nome = fn (?Participante $p): string => $p?->nome() ?? 'o caixa';
            $lancar = fn (Participante $participante, DirecaoPagamento $direcao, string $observacao) => $jogo->pagamentos()->create([
                'uuid' => (string) Str::uuid(),
                'participante_id' => $participante->id,
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

            return $acerto;
        });
    }
}
