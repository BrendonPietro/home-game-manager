<?php

namespace App\Services\Jogo;

use App\Enums\TipoAlteracao;
use App\Models\Alteracao;
use App\Models\Jogo;
use App\Models\Seguranca;
use App\Models\Usuario;
use Illuminate\Support\Str;

/**
 * Histórico de alterações do jogo (quem mudou o quê e quando). As Actions chamam dentro da própria
 * transação: se a ação não acontece, o registro também não fica.
 */
class Historico
{
    /** @param  array<string, mixed>  $dados  antes e depois, para conferir depois */
    public function registrar(
        Jogo $jogo,
        Usuario|Seguranca|null $quem,
        TipoAlteracao $tipo,
        string $descricao,
        ?string $motivo = null,
        array $dados = [],
    ): Alteracao {
        return Alteracao::query()->create([
            'jogo_id' => $jogo->id,
            'usuario_id' => $quem instanceof Usuario ? $quem->id : null,
            'seguranca_id' => $quem instanceof Seguranca ? $quem->id : null,
            'tipo' => $tipo,
            'descricao' => Str::limit($descricao, 255, ''),
            'motivo' => filled($motivo) ? Str::limit(trim($motivo), 255, '') : null,
            'dados' => $dados ?: null,
        ]);
    }
}
