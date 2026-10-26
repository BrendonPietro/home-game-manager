<?php

namespace App\Actions\Jogo;

use App\Actions\Jogo\Concerns\ChecaRegras;
use App\Enums\StatusJogo;
use App\Exceptions\RegraDoJogo;
use App\Models\Jogo;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;

/**
 * v1: nível e blinds digitados na tela ao vivo (a estrutura de blinds com relógio é da v2).
 * Blinds ficam como o grupo escreve, só com a barra espaçada: "100/200" → "100 / 200".
 * Vazio limpa. A eliminação guarda o nível da hora.
 */
class AtualizarNivel
{
    use ChecaRegras;

    public function __invoke(Jogo $jogo, ?int $nivel, ?string $blinds, Usuario $quem): Jogo
    {
        $this->permitir($quem, 'jogo.fases', 'Só o Admin do site muda o nível e os blinds.');

        $blinds = preg_replace(['/\s*\/\s*/', '/\s+/'], [' / ', ' '], trim((string) $blinds));

        if ($nivel !== null && ($nivel < 1 || $nivel > 99)) {
            throw new RegraDoJogo('O nível vai de 1 a 99.');
        }

        if (mb_strlen($blinds) > 40) {
            throw new RegraDoJogo('Os blinds cabem em 40 caracteres.');
        }

        return DB::transaction(function () use ($jogo, $nivel, $blinds) {
            $jogo = Jogo::query()->lockForUpdate()->findOrFail($jogo->id);
            $this->exigirStatus($jogo, [StatusJogo::EmAndamento], 'Nível e blinds só com o jogo em andamento.');

            $jogo->update(['nivel_atual' => $nivel, 'blinds_atual' => $blinds !== '' ? $blinds : null]);

            return $jogo;
        });
    }
}
