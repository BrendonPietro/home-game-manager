<?php

namespace App\Policies;

use App\Enums\StatusJogo;
use App\Models\Seguranca;
use Illuminate\Database\Eloquent\Model;

/**
 * Jogos reais (os modelos têm a própria permissão `modelos.*`, na fase 5).
 * Finalizado e cancelado só se veem. Excluir só rascunho sem nenhuma entrada; o resto se cancela.
 * Com `jogos.excluir_tudo` (o Desenvolvedor), exclui qualquer jogo, com o que foi lançado (ExcluirJogo).
 */
class JogoPolicy extends PoliticaDoPainel
{
    protected const RECURSO = 'jogos';

    public function update(Seguranca $conta, Model $jogo): bool
    {
        return parent::update($conta, $jogo)
            && ! in_array($jogo->status, [StatusJogo::Finalizado, StatusJogo::Cancelado], true);
    }

    public function delete(Seguranca $conta, Model $jogo): bool
    {
        return parent::delete($conta, $jogo)
            && ($this->pode($conta, 'excluir_tudo')
                || ($jogo->status === StatusJogo::Rascunho && ! $jogo->entradas()->exists()));
    }
}
