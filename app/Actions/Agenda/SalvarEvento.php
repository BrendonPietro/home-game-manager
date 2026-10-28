<?php

namespace App\Actions\Agenda;

use App\Enums\StatusAluguel;
use App\Enums\TipoEvento;
use App\Models\Evento;
use App\Support\Dinheiro;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Cria ou altera um evento do calendário que não é jogo (aluguel de mesa, reserva, bloqueio), com as
 * mesas e, no aluguel, cliente, valor e status. O evento do jogo vem do cadastro do jogo (EventoDoJogo).
 *
 * Aluguel se chama "Mesa alugada — Cliente"; marcar pago grava `pago_em` (uma vez só).
 */
class SalvarEvento
{
    /** @param  array<string, mixed>  $dados  campos do EventoForm */
    public function __invoke(?Evento $evento, array $dados): Evento
    {
        $tipo = $dados['tipo'] instanceof TipoEvento ? $dados['tipo'] : TipoEvento::from($dados['tipo']);

        if ($tipo === TipoEvento::Jogo || $evento?->tipo === TipoEvento::Jogo) {
            throw new InvalidArgumentException('O evento do jogo vem do cadastro do jogo.');
        }

        return DB::transaction(function () use ($evento, $dados, $tipo): Evento {
            $evento ??= new Evento;
            $eAluguel = $tipo === TipoEvento::Aluguel;
            $inicio = $dados['hora_inicio'] ?? null;

            $evento->fill([
                'tipo' => $tipo,
                'nome' => $eAluguel ? 'Mesa alugada — '.trim($dados['cliente']) : trim($dados['nome']),
                'data' => $dados['data'],
                'hora_inicio' => $inicio,
                'hora_fim' => $inicio ? ($dados['hora_fim'] ?? null) : null,
                'responsavel_id' => $dados['responsavel_id'] ?? null,
                'observacoes' => $dados['observacoes'] ?? null,
            ])->save();

            $evento->mesas()->sync($dados['mesas'] ?? []);

            if ($eAluguel) {
                $status = $dados['status'] instanceof StatusAluguel ? $dados['status'] : StatusAluguel::from($dados['status']);

                $evento->aluguel()->updateOrCreate([], [
                    'cliente' => trim($dados['cliente']),
                    'valor' => Dinheiro::decimal(Dinheiro::centavos($dados['valor'])),
                    'status' => $status,
                    'pago_em' => $status === StatusAluguel::Pago ? ($evento->aluguel?->pago_em ?? now()) : null,
                ]);
            } else {
                $evento->aluguel()->delete();
            }

            return $evento->refresh();
        });
    }
}
