<?php

namespace App\Policies;

use App\Models\Seguranca;
use Illuminate\Database\Eloquent\Model;

class RankingPolicy extends PoliticaDoPainel
{
    protected const RECURSO = 'rankings';

    /** Ranking com pontos gravados não se exclui (some a classificação): desative. */
    public function delete(Seguranca $conta, Model $ranking): bool
    {
        return parent::delete($conta, $ranking) && ! $ranking->pontos()->exists();
    }
}
