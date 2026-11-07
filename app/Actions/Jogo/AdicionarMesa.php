<?php

namespace App\Actions\Jogo;

use App\Actions\Jogo\Concerns\ChecaRegras;
use App\Enums\StatusJogo;
use App\Models\Jogo;
use App\Models\JogoMesa;
use App\Models\Usuario;
use App\Services\Jogo\EventoDoJogo;
use Illuminate\Support\Facades\DB;

/**
 * Põe mais uma mesa no jogo, no fim da lista: "Mesa N" (o próximo número) com os lugares por mesa do jogo.
 * Não depende do cadastro de mesas (que é dos aluguéis): o torneio pode ter quantas mesas precisar.
 */
class AdicionarMesa
{
    use ChecaRegras;

    public function __construct(private EventoDoJogo $evento) {}

    public function __invoke(Jogo $jogo, Usuario $quem): JogoMesa
    {
        $this->permitir($quem, 'mesas.organizar', 'Você não tem permissão para mudar as mesas.');

        return DB::transaction(function () use ($jogo) {
            $jogo = Jogo::query()->lockForUpdate()->findOrFail($jogo->id);
            $this->exigirStatus($jogo, [StatusJogo::Agendado, StatusJogo::EmAndamento], 'As mesas só mudam com o jogo agendado ou em andamento.');

            $mesas = $jogo->mesas()->get(['nome', 'ordem']);

            $jogoMesa = $jogo->mesas()->create([
                'nome' => self::proximoNome($mesas->pluck('nome')->all()),
                'lugares' => min(12, max(2, (int) $jogo->lugares_por_mesa)),
                'ordem' => (int) $mesas->max('ordem') + 1,
            ]);

            $this->evento->sincronizar($jogo);

            return $jogoMesa;
        });
    }

    /**
     * "Mesa N" com N = o maior número das mesas do jogo + 1 ("Mesa 01", "Mesa 2" → "Mesa 3"); sem número, conta as mesas.
     *
     * @param  list<?string>  $nomes
     */
    public static function proximoNome(array $nomes): string
    {
        $numeros = array_map(
            fn (?string $nome): int => preg_match('/(\d+)\s*$/', (string) $nome, $achou) ? (int) $achou[1] : 0,
            $nomes,
        );

        return 'Mesa '.(max([count($nomes), ...$numeros]) + 1);
    }
}
