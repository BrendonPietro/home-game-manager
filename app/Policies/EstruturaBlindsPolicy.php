<?php

namespace App\Policies;

use App\Models\Seguranca;
use Illuminate\Database\Eloquent\Model;

class EstruturaBlindsPolicy extends PoliticaDoPainel
{
    protected const RECURSO = 'estruturas';

    /** Estrutura usada num jogo ou modelo não se exclui: desative. */
    public function delete(Seguranca $conta, Model $estrutura): bool
    {
        return parent::delete($conta, $estrutura) && ! $estrutura->jogos()->exists();
    }
}
