<?php

namespace App\Services\Jogo;

use App\Enums\StatusJogo;
use App\Models\EstruturaNivel;
use App\Models\Jogo;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Relógio do torneio com estrutura de blinds (docs/regras.md, "Blinds e relógio"). Sem cron nem
 * WebSocket: o banco guarda o nível, os segundos que faltavam e desde quando o relógio corre
 * (`relogio_desde`, nulo = pausado); o estado de agora sai da conta, andando pelos níveis que já
 * passaram. No fim do último nível o relógio para em 00:00.
 *
 * `jogos.nivel_atual` e `blinds_atual` seguem o relógio: `sincronizar` grava quando o nível muda
 * (a tela ao vivo chama a cada atualização; as Actions que guardam o nível chamam `avancar` com a
 * linha já travada).
 */
class Relogio
{
    /** Estado agora (ou em `$agora`). Nulo quando o jogo não tem relógio. */
    public function estado(Jogo $jogo, ?CarbonInterface $agora = null): ?EstadoDoRelogio
    {
        $agora ??= now();
        $niveis = $this->niveis($jogo);

        if ($jogo->status !== StatusJogo::EmAndamento || $niveis->isEmpty()) {
            return null;
        }

        [$indice, $restante] = $this->guardado($jogo, $niveis);
        $rodando = $jogo->relogio_desde !== null;
        $fracao = 0.0;

        if ($rodando) {
            $passou = max(0.0, (float) $jogo->relogio_desde->diffInSeconds($agora, false));
            $restante -= (int) floor($passou);
            $fracao = $passou - floor($passou);

            while ($restante <= 0 && isset($niveis[$indice + 1])) {
                $restante += $niveis[++$indice]->duracao_minutos * 60;
            }

            if ($restante <= 0) {
                [$restante, $rodando, $fracao] = [0, false, 0.0];
            }
        }

        $linhas = $this->numeros($niveis);

        return new EstadoDoRelogio(
            niveis: $niveis,
            indice: $indice,
            numero: $linhas[$indice]['numero'],
            ultimoNumero: $linhas[$indice]['ultimo'],
            restante: $restante,
            rodando: $rodando,
            iniciado: $jogo->relogio_iniciado_em !== null,
            agora: $agora,
            fracao: $fracao,
        );
    }

    /** Grava o nível quando o relógio passou para outro (ou parou no fim). Devolve o jogo atualizado. */
    public function sincronizar(Jogo $jogo): Jogo
    {
        $estado = $this->estado($jogo);

        if ($estado === null || ! $this->mudou($jogo, $estado)) {
            return $jogo;
        }

        return DB::transaction(function () use ($jogo): Jogo {
            $travado = Jogo::query()->lockForUpdate()->findOrFail($jogo->id);
            $this->avancar($travado);

            return $travado;
        });
    }

    /** O mesmo que `sincronizar`, para quem já travou a linha do jogo numa transação. */
    public function avancar(Jogo $travado, ?CarbonInterface $agora = null): void
    {
        $estado = $this->estado($travado, $agora);

        if ($estado !== null && $this->mudou($travado, $estado)) {
            $this->posicionar($travado, $estado->nivel(), $estado->restante, $estado->rodando, $estado->agora);
        }
    }

    /** Põe o relógio num nível, com os segundos que faltam, correndo ou parado (linha já travada). */
    public function posicionar(Jogo $travado, EstruturaNivel $nivel, int $segundos, bool $rodando, ?CarbonInterface $agora = null): void
    {
        $agora ??= now();
        $linha = collect($this->numeros($this->niveis($travado)))->firstWhere('id', $nivel->id);

        $travado->forceFill([
            'relogio_nivel_id' => $nivel->id,
            'relogio_segundos' => max(0, $segundos),
            'relogio_desde' => $rodando ? $agora : null,
            'relogio_iniciado_em' => $travado->relogio_iniciado_em ?? ($rodando ? $agora : null),
            'nivel_atual' => $linha['ultimo'] ?? null,
            'blinds_atual' => $nivel->blindsComAnte(),
        ])->save();
    }

    /** Ao iniciar o jogo: nível 1 com o tempo cheio, parado até alguém apertar INICIAR (linha travada). */
    public function preparar(Jogo $travado): void
    {
        if ($primeiro = $this->niveis($travado)->first()) {
            $this->posicionar($travado, $primeiro, $primeiro->duracao_minutos * 60, false);
        }
    }

    /** @return Collection<int, EstruturaNivel> */
    private function niveis(Jogo $jogo): Collection
    {
        if (! $jogo->temRelogio()) {
            return collect();
        }

        return $jogo->estruturaBlinds->niveis->values();
    }

    /**
     * Nível e segundos guardados. Se o nível guardado sumiu da estrutura (ou o jogo começou antes
     * da v2), vale o nível de blinds com o número de `nivel_atual`, ou o primeiro.
     *
     * @param  Collection<int, EstruturaNivel>  $niveis
     * @return array{int, int}
     */
    private function guardado(Jogo $jogo, Collection $niveis): array
    {
        $indice = $niveis->search(fn (EstruturaNivel $n) => $n->id === $jogo->relogio_nivel_id);

        if ($indice === false) {
            $numeros = $this->numeros($niveis);
            $indice = collect($numeros)->search(fn (array $l) => $l['numero'] !== null && $l['numero'] === $jogo->nivel_atual);
            $indice = $indice === false ? 0 : $indice;
        }

        return [$indice, $jogo->relogio_segundos ?? $niveis[$indice]->duracao_minutos * 60];
    }

    /**
     * Número de cada nível (intervalo não conta) e o último nível de blinds até ele.
     *
     * @param  Collection<int, EstruturaNivel>  $niveis
     * @return list<array{id: int, numero: ?int, ultimo: ?int}>
     */
    private function numeros(Collection $niveis): array
    {
        $numero = 0;

        return $niveis->map(function (EstruturaNivel $nivel) use (&$numero): array {
            $proprio = $nivel->e_intervalo ? null : ++$numero;

            return ['id' => $nivel->id, 'numero' => $proprio, 'ultimo' => $numero ?: null];
        })->all();
    }

    private function mudou(Jogo $jogo, EstadoDoRelogio $estado): bool
    {
        return $jogo->relogio_nivel_id !== $estado->nivel()->id
            || ($jogo->relogio_desde !== null) !== $estado->rodando
            || $jogo->nivel_atual !== $estado->ultimoNumero
            || $jogo->blinds_atual !== $estado->nivel()->blindsComAnte();
    }
}
