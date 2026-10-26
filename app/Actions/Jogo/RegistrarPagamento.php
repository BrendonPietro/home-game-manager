<?php

namespace App\Actions\Jogo;

use App\Actions\Jogo\Concerns\ChecaRegras;
use App\Enums\DirecaoPagamento;
use App\Enums\FormaPagamento;
use App\Enums\StatusJogo;
use App\Exceptions\RegraDoJogo;
use App\Models\Jogo;
use App\Models\Pagamento;
use App\Models\Participante;
use App\Models\Usuario;
use App\Support\Dinheiro;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Pagamento parcial ou total entre jogador e caixa: o jogador pagou ou recebeu. Vale com o jogo
 * agendado (pagou antes de começar), em andamento e finalizado (pendências).
 * O uuid vem do clique: repetido devolve o pagamento que já existe (toque duplo).
 */
class RegistrarPagamento
{
    use ChecaRegras;

    /** Teto do decimal(10,2): R$ 99.999.999,99. */
    private const MAXIMO = 9_999_999_999;

    public function __invoke(
        Participante $participante,
        DirecaoPagamento $direcao,
        int $centavos,
        FormaPagamento $forma,
        Usuario $quem,
        string $uuid,
        ?string $observacao = null,
    ): Pagamento {
        $this->permitir($quem, 'pagamentos.registrar', 'Você não tem permissão para registrar pagamentos.');

        if (! Str::isUuid($uuid)) {
            throw new InvalidArgumentException("uuid inválido: [{$uuid}]");
        }

        if ($centavos <= 0) {
            throw new RegraDoJogo('Informe um valor maior que zero.');
        }

        if ($centavos > self::MAXIMO) {
            throw new RegraDoJogo('Valor alto demais: confira os dígitos.');
        }

        return DB::transaction(function () use ($participante, $direcao, $centavos, $forma, $quem, $uuid, $observacao) {
            $jogo = Jogo::query()->lockForUpdate()->findOrFail($participante->jogo_id);

            if ($repetido = Pagamento::query()->where('uuid', $uuid)->first()) {
                return $repetido;
            }

            $this->exigirStatus(
                $jogo,
                [StatusJogo::Agendado, StatusJogo::EmAndamento, StatusJogo::Finalizado],
                'Pagamentos só com o jogo agendado, em andamento ou finalizado.',
            );

            $participante = $jogo->participantes()->findOrFail($participante->id);
            $observacao = Str::limit(trim((string) $observacao), 255, '');

            return $jogo->pagamentos()->create([
                'uuid' => $uuid,
                'participante_id' => $participante->id,
                'direcao' => $direcao,
                'valor' => Dinheiro::decimal($centavos),
                'forma' => $forma,
                'observacao' => $observacao !== '' ? $observacao : null,
                'registrado_por' => $quem->id,
            ]);
        });
    }
}
