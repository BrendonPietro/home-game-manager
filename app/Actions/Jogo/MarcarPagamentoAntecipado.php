<?php

namespace App\Actions\Jogo;

use App\Actions\Jogo\Concerns\ChecaRegras;
use App\Enums\StatusJogo;
use App\Enums\TipoAlteracao;
use App\Enums\TipoItem;
use App\Exceptions\RegraDoJogo;
use App\Models\Jogo;
use App\Models\Participante;
use App\Models\Seguranca;
use App\Models\Usuario;
use App\Services\Jogo\Historico;
use App\Support\Fichas;
use Illuminate\Support\Facades\DB;

/**
 * Marca (ou desmarca) que o jogador pagou o buy-in antes: ele ganha as fichas do bônus do pagamento antecipado
 * (`jogos.antecipado_fichas`) junto do buy-in. Quem marca decide, sem olhar data nem valor, com o jogo agendado
 * ou em andamento: pelo painel (aba Confirmados) ou pelo site (lista da noite e painel do jogador), com a
 * permissão `pagamentos.antecipado` dos dois lados.
 *
 * Ainda sem buy-in, o bônus entra quando ele sentar (RegistrarEntrada). Já sentado, o buy-in ganha (ou perde)
 * as fichas na hora, e o aviso diz quanto entregar. Fica no histórico do jogo.
 */
class MarcarPagamentoAntecipado
{
    use ChecaRegras;

    public const PERMISSAO = 'pagamentos.antecipado';

    public function __construct(private Historico $historico) {}

    public function __invoke(Participante $participante, bool $pagou, Usuario|Seguranca $quem): Participante
    {
        $this->permitirNosDois($quem, self::PERMISSAO, self::PERMISSAO, 'Você não tem permissão para marcar o pagamento antecipado.');

        return DB::transaction(function () use ($participante, $pagou, $quem) {
            $jogo = Jogo::query()->lockForUpdate()->findOrFail($participante->jogo_id);

            $this->exigirStatus($jogo, [StatusJogo::Agendado, StatusJogo::EmAndamento], 'O pagamento antecipado se marca com o jogo agendado ou em andamento.');

            if (! $jogo->temBonusAntecipado()) {
                throw new RegraDoJogo('Este torneio não tem bônus do pagamento antecipado: defina as fichas no cadastro do jogo, aba Blinds.');
            }

            $participante = $jogo->participantes()->with('usuario')->findOrFail($participante->id);

            if ($participante->pagou_antes === $pagou) {
                return $participante;
            }

            $participante->forceFill(['pagou_antes' => $pagou])->save();

            $buyin = $participante->entradas()->validas()->where('tipo', TipoItem::Buyin)->first();
            $buyin?->forceFill(['antecipado' => self::fichas($jogo, $participante)])->save();

            $bonus = Fichas::quantas((int) $jogo->antecipado_fichas);
            $this->historico->registrar(
                $jogo,
                $quem,
                TipoAlteracao::PagamentoAntecipado,
                $pagou
                    ? "{$participante->nome()} pagou antes: ganha {$bonus}".($buyin ? ' no buy-in já lançado' : ' ao sentar')
                    : "{$participante->nome()} sem o pagamento antecipado".($buyin ? ": o buy-in perde as {$bonus}" : ''),
                dados: ['participante_id' => $participante->id, 'pagou_antes' => $pagou, 'fichas' => (int) $jogo->antecipado_fichas],
            );

            return $participante;
        });
    }

    /** Fichas do bônus para o buy-in deste jogador (0 quando ele não pagou antes ou o jogo não tem bônus). */
    public static function fichas(Jogo $jogo, Participante $participante): int
    {
        return $participante->pagou_antes && $jogo->temBonusAntecipado() ? (int) $jogo->antecipado_fichas : 0;
    }

    /** O que mostrar depois de marcar: "Fulano pagou antes: entregar 20.000 fichas a mais." */
    public static function aviso(Participante $participante): string
    {
        $jogo = $participante->jogo;
        $bonus = Fichas::quantas((int) $jogo->antecipado_fichas);
        $sentou = $participante->entradas()->validas()->where('tipo', TipoItem::Buyin)->exists();

        return $participante->nome().match (true) {
            $participante->pagou_antes && $sentou => " pagou antes: entregar {$bonus} a mais.",
            $participante->pagou_antes => " pagou antes: ganha {$bonus} a mais ao sentar.",
            $sentou => " sem o pagamento antecipado: o buy-in perde as {$bonus}.",
            default => ' sem o pagamento antecipado.',
        };
    }
}
