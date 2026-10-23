<?php

namespace App\Actions\Jogo;

use App\Actions\Jogo\Concerns\ChecaRegras;
use App\Enums\StatusJogo;
use App\Exceptions\RegraDoJogo;
use App\Models\Jogo;
use App\Models\JogoMesa;
use App\Models\Usuario;
use App\Services\Jogo\EventoDoJogo;
use Illuminate\Support\Facades\DB;

/** Tira uma mesa do jogo, se ninguém estiver sentado nela. A agenda acompanha. */
class RemoverMesa
{
    use ChecaRegras;

    public function __construct(private EventoDoJogo $evento) {}

    public function __invoke(JogoMesa $jogoMesa, Usuario $quem): void
    {
        $this->permitir($quem, 'mesas.organizar', 'Você não tem permissão para mudar as mesas.');

        DB::transaction(function () use ($jogoMesa) {
            $jogo = Jogo::query()->lockForUpdate()->findOrFail($jogoMesa->jogo_id);
            $this->exigirStatus($jogo, [StatusJogo::Agendado, StatusJogo::EmAndamento], 'As mesas só mudam com o jogo agendado ou em andamento.');

            if ($jogoMesa->participantes()->exists()) {
                throw new RegraDoJogo("Tire os jogadores da {$jogoMesa->mesa->nome} antes de removê-la.");
            }

            $jogoMesa->delete();
            $this->evento->sincronizar($jogo);
        });
    }
}
