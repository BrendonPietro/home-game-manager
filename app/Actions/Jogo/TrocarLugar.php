<?php

namespace App\Actions\Jogo;

use App\Actions\Jogo\Concerns\ChecaRegras;
use App\Enums\StatusJogo;
use App\Enums\StatusParticipante;
use App\Enums\TipoItem;
use App\Exceptions\RegraDoJogo;
use App\Models\Jogo;
use App\Models\JogoMesa;
use App\Models\Participante;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;

/**
 * Leva o jogador para um lugar de uma mesa. Lugar ocupado: os dois trocam (quem estava vai para o
 * lugar antigo, ou fica sem lugar); se quem estava já saiu do jogo (eliminado, cash-out), ele só
 * guardava o lugar e fica sem lugar. Mesa nula: o jogador fica sem lugar. Serve para montar as mesas
 * e para a quebra de mesa durante o jogo; quem chegou atrasado e senta com o jogo em andamento
 * passa a jogando e, no torneio, já entra com o buy-in padrão (sentar é entrar). Com as entradas
 * fechadas, ninguém novo senta.
 */
class TrocarLugar
{
    use ChecaRegras;

    public function __construct(private RegistrarEntrada $entrada) {}

    public function __invoke(Participante $participante, ?JogoMesa $mesa, ?int $lugar, Usuario $quem): void
    {
        $this->permitir($quem, 'mesas.organizar', 'Você não tem permissão para mudar os lugares.');

        DB::transaction(function () use ($participante, $mesa, $lugar, $quem) {
            $jogo = Jogo::query()->lockForUpdate()->findOrFail($participante->jogo_id);
            $this->exigirStatus($jogo, [StatusJogo::Agendado, StatusJogo::EmAndamento], 'Os lugares só mudam com o jogo agendado ou em andamento.');

            $participante = $jogo->participantes()->with('usuario')->findOrFail($participante->id);

            if (in_array($participante->status, [StatusParticipante::Eliminado, StatusParticipante::Saiu], true)) {
                throw new RegraDoJogo("{$participante->usuario->nome_exibicao} já saiu do jogo.");
            }

            if ($mesa === null) {
                $participante->update(['jogo_mesa_id' => null, 'lugar' => null]);

                return;
            }

            if ($mesa->jogo_id !== $jogo->id) {
                throw new RegraDoJogo('Essa mesa não é deste jogo.');
            }

            if ($lugar === null || $lugar < 1 || $lugar > $mesa->lugares) {
                throw new RegraDoJogo("A {$mesa->nome} tem {$mesa->lugares} lugares.");
            }

            $chegando = $jogo->status === StatusJogo::EmAndamento && $participante->status === StatusParticipante::Confirmado;

            if ($chegando && $jogo->eTorneio() && ! $jogo->entradasAbertas()) {
                throw new RegraDoJogo("As entradas já foram fechadas: {$participante->usuario->nome_exibicao} não entra mais.");
            }

            [$mesaAntes, $lugarAntes] = [$participante->jogo_mesa_id, $participante->lugar];

            if ($mesaAntes === $mesa->id && $lugarAntes === $lugar) {
                return;
            }

            $ocupante = $jogo->participantes()->where('jogo_mesa_id', $mesa->id)->where('lugar', $lugar)->first();

            // (jogo_mesa_id, lugar) é único: libera o lugar de quem se move antes de trocar.
            $participante->update(['jogo_mesa_id' => null, 'lugar' => null]);
            $ocupante?->update($ocupante->estaFora()
                ? ['jogo_mesa_id' => null, 'lugar' => null]
                : ['jogo_mesa_id' => $mesaAntes, 'lugar' => $lugarAntes]);
            $participante->update(['jogo_mesa_id' => $mesa->id, 'lugar' => $lugar]);

            if ($chegando) {
                $participante->forceFill(['status' => StatusParticipante::Jogando])->save();

                $temBuyin = $participante->entradas()->validas()->where('tipo', TipoItem::Buyin)->exists();

                if ($jogo->eTorneio() && ! $temBuyin) {
                    $this->entrada->buyinPadrao($participante, $quem);
                }
            }
        });
    }
}
