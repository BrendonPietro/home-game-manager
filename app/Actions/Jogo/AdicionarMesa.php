<?php

namespace App\Actions\Jogo;

use App\Actions\Jogo\Concerns\ChecaRegras;
use App\Enums\StatusJogo;
use App\Exceptions\RegraDoJogo;
use App\Models\Jogo;
use App\Models\JogoMesa;
use App\Models\Mesa;
use App\Models\Usuario;
use App\Services\Jogo\EventoDoJogo;
use Illuminate\Support\Facades\DB;

/** Põe mais uma mesa física no jogo (com os lugares dela), no fim da lista. A agenda acompanha. */
class AdicionarMesa
{
    use ChecaRegras;

    public function __construct(private EventoDoJogo $evento) {}

    public function __invoke(Jogo $jogo, Mesa $mesa, Usuario $quem): JogoMesa
    {
        $this->permitir($quem, 'mesas.organizar', 'Você não tem permissão para mudar as mesas.');

        return DB::transaction(function () use ($jogo, $mesa) {
            $jogo = Jogo::query()->lockForUpdate()->findOrFail($jogo->id);
            $this->exigirStatus($jogo, [StatusJogo::Agendado, StatusJogo::EmAndamento], 'As mesas só mudam com o jogo agendado ou em andamento.');

            if (! $mesa->ativa) {
                throw new RegraDoJogo("A {$mesa->nome} está inativa no cadastro.");
            }

            if ($jogo->mesas()->where('mesa_id', $mesa->id)->exists()) {
                throw new RegraDoJogo("A {$mesa->nome} já está neste jogo.");
            }

            $jogoMesa = $jogo->mesas()->create([
                'mesa_id' => $mesa->id,
                'lugares' => min(12, max(2, $mesa->lugares)),
                'ordem' => (int) $jogo->mesas()->max('ordem') + 1,
            ]);

            $this->evento->sincronizar($jogo);

            return $jogoMesa;
        });
    }
}
