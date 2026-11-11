<?php

namespace App\Actions\Jogo;

use App\Actions\Jogo\Concerns\ChecaRegras;
use App\Enums\StatusJogo;
use App\Enums\StatusParticipante;
use App\Enums\TipoItem;
use App\Exceptions\RegraDoJogo;
use App\Models\Entrada;
use App\Models\Jogo;
use App\Models\JogoMesa;
use App\Models\Participante;
use App\Models\Usuario;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Sorteia o lugar de quem foi escolhido, nas mesas escolhidas (modal Jogadores, telas 06 e 07): antes de
 * começar e durante o jogo (quem chegou atrasado, quem voltou com rebuy no intervalo, quebra de mesa).
 *
 * - Sem escolher ninguém (nulo): todos os que estão no jogo; antes de começar, os confirmados.
 * - Lugares livres: os vagos e o de quem já saiu (eliminado, cash-out), que só guardava o lugar.
 *   Quem foi escolhido e já estava sentado é sorteado de novo; os outros ficam onde estão.
 * - As mesas ficam equilibradas (cada uma recebe gente na proporção dos seus lugares, contando quem
 *   fica) e o lugar dentro da mesa é aleatório.
 * - Cada lugar passa pelo TrocarLugar: no torneio em andamento, quem senta pela primeira vez já entra
 *   com o buy-in padrão (time chip e bônus de quem pagou antes); com as entradas fechadas, ninguém novo.
 * - Quem caiu e não voltou não é sorteado: primeiro o rebuy (VOLTAR no painel do jogador).
 */
class SortearLugares
{
    use ChecaRegras;

    public function __construct(private TrocarLugar $trocarLugar) {}

    /**
     * @param  list<int>|null  $participantes  ids de quem sorteia (nulo: todos que estão no jogo)
     * @param  list<int>|null  $mesas  ids das mesas do jogo (nulo: todas)
     * @return list<array{participante: Participante, mesa: JogoMesa, lugar: int, buyin: ?Entrada}> na ordem das mesas e dos lugares; `buyin` só de quem entrou agora
     */
    public function __invoke(Jogo $jogo, Usuario $quem, ?array $participantes = null, ?array $mesas = null): array
    {
        $this->permitir($quem, 'mesas.organizar', 'Você não tem permissão para sortear os lugares.');

        return DB::transaction(function () use ($jogo, $quem, $participantes, $mesas) {
            $jogo = Jogo::query()->lockForUpdate()->findOrFail($jogo->id);
            $this->exigirStatus($jogo, [StatusJogo::Agendado, StatusJogo::EmAndamento], 'Os lugares só se sorteiam com o jogo agendado ou em andamento.');

            $todas = $jogo->mesas()->get();

            if ($todas->isEmpty()) {
                throw new RegraDoJogo('Adicione uma mesa antes de sortear.');
            }

            $doSorteio = $mesas === null ? $todas : $todas->whereIn('id', $mesas)->values();

            if ($doSorteio->isEmpty()) {
                throw new RegraDoJogo('Escolha ao menos uma mesa.');
            }

            $noJogo = $jogo->participantes()->with('usuario')->get();
            $jogadores = ($participantes === null
                ? $noJogo->reject(fn (Participante $p) => $p->estaFora())
                : $noJogo->whereIn('id', $participantes))->shuffle()->values();

            if ($jogadores->isEmpty()) {
                throw new RegraDoJogo('Escolha quem vai sentar.');
            }

            $this->conferirQuemSai($jogadores);

            // Quem fica onde está: sentado, ainda no jogo e não escolhido. O lugar de quem saiu conta como livre.
            $ficam = $noJogo->filter(fn (Participante $p) => $p->jogo_mesa_id !== null && ! $p->estaFora() && ! $jogadores->contains('id', $p->id));
            $livres = $doSorteio->mapWithKeys(fn (JogoMesa $m) => [
                $m->id => collect(range(1, $m->lugares))->diff($ficam->where('jogo_mesa_id', $m->id)->pluck('lugar'))->values(),
            ]);

            $faltam = $jogadores->count() - $livres->sum(fn (Collection $l) => $l->count());

            if ($faltam > 0) {
                $quantos = $faltam === 1 ? 'Falta 1 lugar' : "Faltam {$faltam} lugares";

                throw new RegraDoJogo($doSorteio->count() < $todas->count()
                    ? "{$quantos} nas mesas escolhidas: marque mais mesas ou aumente os lugares."
                    : "{$quantos}: adicione uma mesa ou aumente os lugares.");
            }

            $novos = $this->dividir($doSorteio, $livres, $ficam, $jogadores->count());

            // Quem vai ser sorteado sai do lugar antes: assim ninguém troca de lugar com ninguém.
            $jogo->participantes()->whereIn('id', $jogadores->pluck('id'))->update(['jogo_mesa_id' => null, 'lugar' => null]);

            $sorteados = [];
            $proximo = 0;

            foreach ($doSorteio as $mesa) {
                foreach ($livres[$mesa->id]->shuffle()->take($novos[$mesa->id])->sort() as $lugar) {
                    $participante = $jogadores[$proximo++]->refresh();
                    $chegando = $jogo->status === StatusJogo::EmAndamento && $participante->status === StatusParticipante::Confirmado;

                    ($this->trocarLugar)($participante, $mesa, $lugar, $quem);

                    $sorteados[] = [
                        'participante' => $participante->refresh()->load('usuario'),
                        'mesa' => $mesa,
                        'lugar' => $lugar,
                        'buyin' => $chegando && $jogo->eTorneio()
                            ? $participante->entradas()->validas()->where('tipo', TipoItem::Buyin)->latest('id')->first()
                            : null,
                    ];
                }
            }

            return $sorteados;
        });
    }

    /** @param  Collection<int, Participante>  $jogadores */
    private function conferirQuemSai(Collection $jogadores): void
    {
        foreach ($jogadores as $participante) {
            if ($participante->status === StatusParticipante::Eliminado) {
                throw new RegraDoJogo("{$participante->nome()} caiu: faça o rebuy (VOLTAR no painel do jogador) antes de sortear o lugar.");
            }

            if ($participante->status === StatusParticipante::Saiu) {
                throw new RegraDoJogo("{$participante->nome()} já saiu do jogo.");
            }
        }
    }

    /**
     * Quantos novos vão para cada mesa: sempre a menos cheia (na proporção dos lugares, contando quem
     * fica) que ainda tem lugar livre; no empate, a primeira mesa.
     *
     * @param  Collection<int, JogoMesa>  $mesas
     * @param  Collection<int, Collection<int, int>>  $livres
     * @param  Collection<int, Participante>  $ficam
     * @return array<int, int> jogo_mesa_id => quantos
     */
    private function dividir(Collection $mesas, Collection $livres, Collection $ficam, int $total): array
    {
        $ocupados = $mesas->mapWithKeys(fn (JogoMesa $m) => [$m->id => $ficam->where('jogo_mesa_id', $m->id)->count()])->all();
        $novos = $mesas->mapWithKeys(fn (JogoMesa $m) => [$m->id => 0])->all();

        for ($i = 0; $i < $total; $i++) {
            $mesa = $mesas
                ->filter(fn (JogoMesa $m) => $novos[$m->id] < $livres[$m->id]->count())
                ->sortBy(fn (JogoMesa $m) => sprintf('%08.4f-%04d', ($ocupados[$m->id] + $novos[$m->id]) / $m->lugares, $m->ordem))
                ->first();
            $novos[$mesa->id]++;
        }

        return $novos;
    }
}
