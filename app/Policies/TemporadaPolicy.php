<?php

namespace App\Policies;

use App\Models\Seguranca;
use Illuminate\Database\Eloquent\Model;

class TemporadaPolicy extends PoliticaDoPainel
{
    protected const RECURSO = 'temporadas';

    /** Temporada que já tem jogo não se exclui. */
    public function delete(Seguranca $conta, Model $temporada): bool
    {
        return parent::delete($conta, $temporada) && ! $temporada->jogos()->exists();
    }
}
