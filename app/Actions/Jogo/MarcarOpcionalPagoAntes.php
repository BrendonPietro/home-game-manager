<?php

namespace App\Actions\Jogo;

use App\Actions\Jogo\Concerns\ChecaRegras;
use App\Enums\StatusJogo;
use App\Enums\TipoAlteracao;
use App\Enums\TipoItem;
use App\Exceptions\RegraDoJogo;
use App\Models\Item;
use App\Models\Jogo;
use App\Models\Participante;
use App\Models\Seguranca;
use App\Models\Usuario;
use App\Services\Jogo\Historico;
use App\Support\Fichas;
use Illuminate\Support\Facades\DB;

/**
 * Marca (ou desmarca) que o jogador pagou antes um opcional (taxa da pizza…): o opcional com "Pago antes" no
 * cadastro do jogo (`jogo_itens.pago_antes`) entra junto do buy-in, com as fichas, quando ele sentar
 * (RegistrarEntrada). Quem marca decide, com o jogo agendado ou em andamento, pelo painel (aba Confirmados) ou
 * pelo site (lista da noite e painel do jogador), com a permissão `pagamentos.antecipado` dos dois lados.
 *
 * Já sentado, marcar lança o opcional na hora, junto do buy-in; desmarcar tira o que tinha entrado junto do
 * buy-in (até fechar as entradas). Comprado na hora pelo cartão, ele continua (sai pelo DESFAZER). Fica no histórico.
 * Devolve o aviso para quem marcou: "Ana pagou Taxa Comida antes: entregar 40.000 fichas."
 */
class MarcarOpcionalPagoAntes
{
    use ChecaRegras;

    public function __construct(private Historico $historico, private RegistrarEntrada $registrarEntrada) {}

    public function __invoke(Participante $participante, Item $opcional, bool $pagou, Usuario|Seguranca $quem): string
    {
        $this->permitirNosDois($quem, MarcarPagamentoAntecipado::PERMISSAO, MarcarPagamentoAntecipado::PERMISSAO, 'Você não tem permissão para marcar o pagamento antecipado.');

        return DB::transaction(function () use ($participante, $opcional, $pagou, $quem) {
            $jogo = Jogo::query()->lockForUpdate()->findOrFail($participante->jogo_id);

            $this->exigirStatus($jogo, [StatusJogo::Agendado, StatusJogo::EmAndamento], 'O pagamento antecipado se marca com o jogo agendado ou em andamento.');

            $participante = $jogo->participantes()->with('usuario')->findOrFail($participante->id);
            $opcional = $jogo->opcionais()->findOrFail($opcional->id);
            $nome = $participante->nome();

            if ($pagou && (! $opcional->pago_antes || ! $opcional->ativo)) {
                throw new RegraDoJogo("{$opcional->nome} não se paga antes: ligue \"Pago antes\" no opcional, no cadastro do jogo.");
            }

            $marcado = $participante->opcionaisPagos()->whereKey($opcional->id)->exists();

            if ($marcado === $pagou) {
                return $pagou ? "{$nome} já estava com {$opcional->nome} pago antes." : "{$nome} não estava com {$opcional->nome} pago antes.";
            }

            $buyin = $participante->entradas()->validas()->where('tipo', TipoItem::Buyin)->first();
            $junto = $buyin?->opcionais()->validas()->where('jogo_item_id', $opcional->id)->first();

            if ($buyin && ($pagou || $junto) && ! $jogo->entradasAbertas()) {
                throw new RegraDoJogo("As entradas já foram fechadas: {$opcional->nome} não muda mais no buy-in de {$nome}.");
            }

            $lancado = null;

            if ($pagou) {
                $participante->opcionaisPagos()->attach($opcional->id);
                $lancado = $buyin ? $this->registrarEntrada->juntoDoBuyin($jogo, $participante, $opcional, $buyin, $quem) : null;
            } else {
                $participante->opcionaisPagos()->detach($opcional->id);
                $junto?->cancelar($quem, 'Não pagou antes');
            }

            $fichas = Fichas::quantas((int) $opcional->fichas);
            $aviso = match (true) {
                $pagou && $lancado !== null => "{$nome} pagou {$opcional->nome} antes".($opcional->fichas ? ": entregar {$fichas}." : ': entrou junto do buy-in.'),
                $pagou && $buyin !== null => "{$nome} pagou {$opcional->nome} antes (já tinha comprado na hora).",
                $pagou => "{$nome} pagou {$opcional->nome} antes: entra junto do buy-in".($opcional->fichas ? ", com {$fichas}." : '.'),
                $junto !== null => "{$nome} não pagou {$opcional->nome} antes: saiu do buy-in".($opcional->fichas ? " (recolher {$fichas})." : '.'),
                default => "{$nome} não pagou {$opcional->nome} antes.",
            };

            $this->historico->registrar(
                $jogo,
                $quem,
                TipoAlteracao::PagamentoAntecipado,
                $aviso,
                dados: [
                    'participante_id' => $participante->id,
                    'jogo_item_id' => $opcional->id,
                    'pago_antes' => $pagou,
                    'entrada_id' => ($pagou ? $lancado : $junto)?->id,
                ],
            );

            return $aviso;
        });
    }
}
