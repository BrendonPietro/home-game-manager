<?php

namespace App\Actions\Jogo;

use App\Actions\Jogo\Concerns\ChecaRegras;
use App\Enums\StatusJogo;
use App\Enums\TipoAlteracao;
use App\Exceptions\RegraDoJogo;
use App\Models\Acerto;
use App\Models\Jogo;
use App\Models\Participante;
use App\Models\Usuario;
use App\Services\Jogo\Acertos;
use App\Services\Jogo\Historico;
use App\Support\Dinheiro;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * FEITO marcado errado (permissão `jogo.corrigir`, com motivo, no histórico): os pagamentos que o acerto
 * gerou ficam cancelados, o acerto volta a estar em aberto e a lista dos em aberto é refeita com os
 * saldos de agora. Só com o jogo finalizado.
 */
class DesfazerAcerto
{
    use ChecaRegras;

    public function __construct(
        private Acertos $acertos,
        private Historico $historico,
    ) {}

    public function __invoke(Acerto $acerto, string $motivo, Usuario $quem): void
    {
        $this->permitir($quem, 'jogo.corrigir', 'Você não tem permissão para desfazer acertos.');

        $motivo = Str::limit(trim($motivo), 255, '');

        if ($motivo === '') {
            throw new RegraDoJogo('Diga o motivo para desfazer.');
        }

        DB::transaction(function () use ($acerto, $motivo, $quem) {
            $jogo = Jogo::query()->lockForUpdate()->findOrFail($acerto->jogo_id);
            $this->exigirStatus($jogo, [StatusJogo::Finalizado], 'Os acertos só se desfazem depois de finalizar.');

            $acerto = $jogo->acertos()->with(['de.usuario', 'para.usuario'])->find($acerto->id)
                ?? throw new RegraDoJogo(MarcarAcerto::MUDOU);

            if (! $acerto->pago) {
                throw new RegraDoJogo('Esse acerto ainda não foi feito.');
            }

            $acerto->pagamentos()->validas()->get()->each->cancelar($quem, $motivo);
            $acerto->forceFill(['pago' => false, 'pago_em' => null])->save();

            $nome = fn (?Participante $p): string => $p?->nome() ?? 'o caixa';
            $descricao = 'Desfez o acerto: '.$nome($acerto->de).' → '.$nome($acerto->para).' '.Dinheiro::brl($acerto->valor);

            $this->acertos->refazerEmAberto($jogo);

            $this->historico->registrar($jogo, $quem, TipoAlteracao::DesfezAcerto, $descricao, $motivo, [
                'de' => $acerto->de_participante_id,
                'para' => $acerto->para_participante_id,
                'valor' => $acerto->valor,
            ]);
        });
    }
}
