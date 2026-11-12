<?php

namespace App\Actions\Jogo;

use App\Actions\Jogo\Concerns\ChecaRegras;
use App\Enums\FaseTorneio;
use App\Enums\StatusJogo;
use App\Enums\StatusParticipante;
use App\Enums\TipoItem;
use App\Exceptions\RegraDoJogo;
use App\Models\Entrada;
use App\Models\Item;
use App\Models\Jogo;
use App\Models\Participante;
use App\Models\Seguranca;
use App\Models\Usuario;
use App\Services\Jogo\TimeChip;
use App\Support\Fichas;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Lança buy-in, rebuy ou add-on com os opcionais marcados, ou um opcional "1 por jogador" sozinho.
 * Valor, fichas e o percentual da premiação são copiados do item; os opcionais obrigatórios entram sem marcar.
 *
 * - Buy-in: um por jogador (qualquer variação); rebuy e add-on pedem buy-in.
 * - Torneio: buy-in e rebuy até Fechar entradas (o rebuy também no intervalo), add-on só no intervalo.
 *   Cash game: buy-in e recompra (rebuy) enquanto estiver em andamento; sem add-on.
 * - Rebuy de quem caiu desfaz a eliminação provisória: ele volta para o lugar.
 * - O uuid vem do clique: repetido devolve a entrada que já existe (toque duplo).
 * - Buy-in dentro do prazo do time chip guarda as fichas a mais em `entradas.time_chip` (Services\Jogo\TimeChip);
 *   de quem pagou antes (`participantes.pagou_antes`), as do bônus em `entradas.antecipado` (MarcarPagamentoAntecipado).
 * - Opcional: o "1 por jogador" uma vez; o "junto de cada entrada", marcado ao lançar a entrada ou sozinho
 *   (cartão do painel do jogador), um para cada buy-in, rebuy ou add-on que ele já tem.
 */
class RegistrarEntrada
{
    use ChecaRegras;

    public function __construct(private TimeChip $timeChip) {}

    /** @param  list<int>  $opcionais  ids dos opcionais marcados */
    public function __invoke(Participante $participante, Item $item, Usuario $quem, string $uuid, array $opcionais = []): Entrada
    {
        $this->permitir($quem, 'entradas.registrar', 'Você não tem permissão para lançar entradas.');

        if (! Str::isUuid($uuid)) {
            throw new InvalidArgumentException("uuid inválido: [{$uuid}]");
        }

        return DB::transaction(function () use ($participante, $item, $quem, $uuid, $opcionais) {
            $jogo = Jogo::query()->lockForUpdate()->findOrFail($participante->jogo_id);

            if ($repetida = Entrada::query()->where('uuid', $uuid)->first()) {
                return $repetida;
            }

            $this->exigirStatus($jogo, [StatusJogo::EmAndamento], 'Entradas só com o jogo em andamento.');

            $participante = $jogo->participantes()->with('usuario')->findOrFail($participante->id);
            $item = $jogo->itens()->findOrFail($item->id);

            if (! $item->ativo) {
                throw new RegraDoJogo("{$item->nome} está desativado neste jogo.");
            }

            $this->checarFase($jogo, $item->tipo);
            $this->checarJogador($jogo, $participante, $item);

            if ($this->noLimite($participante, $item)) {
                throw new RegraDoJogo("{$participante->nome()} já comprou {$item->nome}".($item->porJogador() ? '.' : " ({$item->limite_por_jogador}× no máximo)."));
            }

            $buyin = $item->tipo === TipoItem::Buyin;
            $entrada = $this->lancar($jogo, $participante, $item, $quem, $uuid,
                timeChip: $buyin ? $this->timeChip->fichasAgora($jogo) : 0,
                antecipado: $buyin ? MarcarPagamentoAntecipado::fichas($jogo, $participante) : 0,
            );

            if ($item->tipo->eEntrada()) {
                // No buy-in, os opcionais que ele pagou antes (taxa da pizza…) vão junto, como os obrigatórios.
                $pagosAntes = $buyin ? $participante->opcionaisPagos()->pluck('jogo_itens.id')->all() : [];

                foreach ($this->opcionaisJunto($jogo, $participante, $item->tipo, $opcionais, $pagosAntes) as $opcional) {
                    $this->lancar($jogo, $participante, $opcional, $quem, (string) Str::uuid(), $entrada);
                }
            }

            if ($item->tipo === TipoItem::Rebuy) {
                $this->voltar($participante, $entrada);
            }

            return $entrada;
        });
    }

    /**
     * Torneio: sentar na mesa já é entrar. Lança o buy-in padrão (a linha marcada como padrão, senão a
     * primeira ativa) com os opcionais obrigatórios; outra variação (buy-in duplo) se troca desfazendo.
     */
    public function buyinPadrao(Participante $participante, Usuario $quem): Entrada
    {
        $buyins = $participante->jogo->itens()->ativos()->where('tipo', TipoItem::Buyin)->get();
        $item = $buyins->firstWhere('padrao', true) ?? $buyins->first()
            ?? throw new RegraDoJogo('O jogo não tem buy-in ativo: ajuste no painel.');

        return ($this)($participante, $item, $quem, (string) Str::uuid());
    }

    private function checarFase(Jogo $jogo, TipoItem $tipo): void
    {
        if (! $jogo->entradasAbertas()) {
            throw new RegraDoJogo('As entradas já foram fechadas.');
        }

        if ($jogo->eCashGame()) {
            if ($tipo === TipoItem::Addon) {
                throw new RegraDoJogo('Cash game não tem add-on.');
            }

            return;
        }

        // Rebuy vale até fechar as entradas (as fases rebuys e intervalo); add-on só no intervalo.
        if ($tipo === TipoItem::Addon && $jogo->fase !== FaseTorneio::Intervalo) {
            throw new RegraDoJogo('Add-on só no intervalo: aperte ABRIR INTERVALO.');
        }
    }

    private function checarJogador(Jogo $jogo, Participante $participante, Item $item): void
    {
        $nome = $participante->nome();

        if ($participante->status === StatusParticipante::Saiu) {
            throw new RegraDoJogo("{$nome} já saiu do jogo.");
        }

        if ($participante->status === StatusParticipante::Eliminado && $item->tipo !== TipoItem::Rebuy) {
            throw new RegraDoJogo("{$nome} está fora: só volta com rebuy.");
        }

        $temBuyin = $participante->entradas()->validas()->where('tipo', TipoItem::Buyin)->exists();

        if ($item->tipo === TipoItem::Buyin && $temBuyin) {
            throw new RegraDoJogo("{$nome} já tem buy-in. Para mais fichas, ".($jogo->eCashGame() ? 'recompra.' : 'rebuy.'));
        }

        if (in_array($item->tipo, [TipoItem::Rebuy, TipoItem::Addon], true) && ! $temBuyin) {
            throw new RegraDoJogo("{$nome} ainda não tem buy-in.");
        }

        // Opcional "junto de cada entrada" comprado sozinho (cartão do painel do jogador): um para cada entrada.
        if ($item->tipo === TipoItem::Opcional && ! $item->porJogador() && self::semEntradaLivre($participante, $item)) {
            throw new RegraDoJogo($temBuyin
                ? "{$nome} já comprou {$item->nome} para cada entrada: depois de um rebuy ou add-on, dá para comprar de novo."
                : "{$nome} ainda não tem buy-in.");
        }
    }

    /**
     * Opcional "junto de cada entrada" vai um por entrada (buy-in, rebuy, add-on): comprado na hora, junto do
     * rebuy ou sozinho depois (o buy-in nasce ao sentar, sem a escolha). Sem entrada livre, não tem como comprar.
     */
    public static function semEntradaLivre(Participante $participante, Item $opcional): bool
    {
        $entradas = $participante->entradas()->validas()->whereIn('tipo', $opcional->compravel_com ?? [])->count();

        return $participante->entradas()->validas()->where('jogo_item_id', $opcional->id)->count() >= $entradas;
    }

    /** Opcional "1 por jogador" já comprado, ou item no limite por jogador. */
    private function noLimite(Participante $participante, Item $item): bool
    {
        $limite = $item->porJogador() ? 1 : $item->limite_por_jogador;

        return $limite > 0
            && $participante->entradas()->validas()->where('jogo_item_id', $item->id)->count() >= $limite;
    }

    /**
     * Opcionais que vão junto desta entrada: os marcados, os obrigatórios e, no buy-in, os pagos antes.
     * Obrigatório ou pago antes já comprado (ou no limite) fica de fora sem erro; marcado no limite é erro.
     *
     * @param  list<int>  $marcados
     * @param  list<int>  $pagosAntes  opcionais que ele pagou antes (só no buy-in)
     * @return Collection<int, Item>
     */
    private function opcionaisJunto(Jogo $jogo, Participante $participante, TipoItem $tipo, array $marcados, array $pagosAntes = []): Collection
    {
        $marcados = array_map('intval', $marcados);
        $vendidos = $jogo->opcionais()->ativos()->get()
            ->filter(fn (Item $opcional) => $opcional->porJogador() || $opcional->compravelCom($tipo));

        if (array_diff($marcados, $vendidos->modelKeys())) {
            throw new RegraDoJogo('Esse opcional não é vendido junto de '.$tipo->label().'.');
        }

        return $vendidos->filter(function (Item $opcional) use ($participante, $marcados, $pagosAntes) {
            $marcado = in_array($opcional->id, $marcados, true);
            $sozinho = $opcional->obrigatorio || ($opcional->pago_antes && in_array($opcional->id, $pagosAntes, true));

            if (! $marcado && ! $sozinho) {
                return false;
            }

            if (! $this->noLimite($participante, $opcional)) {
                return true;
            }

            if ($marcado && ! $sozinho) {
                throw new RegraDoJogo("{$participante->nome()} já comprou {$opcional->nome}.");
            }

            return false;
        })->values();
    }

    /**
     * Opcional pago antes marcado em quem já sentou (MarcarOpcionalPagoAntes): entra agora, junto do buy-in,
     * como se tivesse vindo ao sentar. Nulo quando ele já tem (comprou na hora pelo cartão).
     */
    public function juntoDoBuyin(Jogo $jogo, Participante $participante, Item $opcional, Entrada $buyin, Usuario|Seguranca $quem): ?Entrada
    {
        if ($this->noLimite($participante, $opcional) || (! $opcional->porJogador() && self::semEntradaLivre($participante, $opcional))) {
            return null;
        }

        return $this->lancar($jogo, $participante, $opcional, $quem, (string) Str::uuid(), $buyin);
    }

    /** No painel (opcional pago antes marcado com o jogador já sentado), a entrada fica sem `registrado_por`: o histórico diz quem. */
    private function lancar(Jogo $jogo, Participante $participante, Item $item, Usuario|Seguranca $quem, string $uuid, ?Entrada $junto = null, int $timeChip = 0, int $antecipado = 0): Entrada
    {
        return $jogo->entradas()->create([
            'uuid' => $uuid,
            'participante_id' => $participante->id,
            'jogo_item_id' => $item->id,
            'tipo' => $item->tipo,
            'valor' => $item->valor,
            'fichas' => $item->fichas,
            'time_chip' => $timeChip,
            'antecipado' => $antecipado,
            'percentual_premiacao' => $item->percentual_premiacao,
            'vinculada_a' => $junto?->id,
            'registrado_por' => $quem instanceof Usuario ? $quem->id : null,
        ]);
    }

    /** Fichas que o jogador recebe por este lançamento: as da entrada, o time chip, o bônus do pagamento antecipado e as dos opcionais que foram junto. */
    public static function fichasDoLancamento(Entrada $entrada): int
    {
        return (int) $entrada->fichas + (int) $entrada->time_chip + (int) $entrada->antecipado
            + (int) Entrada::query()->validas()->where('vinculada_a', $entrada->id)->sum('fichas');
    }

    /**
     * ": entregar 110.000 fichas (10.000 do time chip, 20.000 do pagamento antecipado, 40.000 de Taxa Comida)", para o
     * aviso do lançamento: o que veio a mais e cada opcional com fichas que foi junto (pago antes, marcado, obrigatório).
     */
    public static function aEntregar(Entrada $entrada): string
    {
        $opcionais = Entrada::query()->validas()->where('vinculada_a', $entrada->id)->where('fichas', '>', 0)->with('item')->orderBy('id')->get()
            ->map(fn (Entrada $opcional): array => [$opcional->item->nome, (int) $opcional->fichas])
            ->all();

        return Fichas::entregar(self::fichasDoLancamento($entrada), (int) $entrada->time_chip, (int) $entrada->antecipado, $opcionais);
    }

    /** Rebuy de quem caiu: a eliminação provisória fica como histórico e ele volta a jogar. */
    private function voltar(Participante $participante, Entrada $rebuy): void
    {
        $eliminacao = $participante->eliminacoes()->validas()->emAberto()->latest('id')->first();

        if ($eliminacao === null) {
            return;
        }

        $eliminacao->update(['voltou_com' => $rebuy->id]);
        $participante->forceFill(['status' => StatusParticipante::Jogando])->save();
    }
}
