<?php

namespace App\Filament\Auth;

use App\Support\AvatarDeFicha;
use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/** Avatar da conta no painel: a ficha com as iniciais (no lugar do ui-avatars.com, que buscava a imagem fora). */
class AvatarDeFichaProvider implements AvatarProvider
{
    public function get(Model|Authenticatable $record): string
    {
        return AvatarDeFicha::dataUri(Filament::getNameForDefaultAvatar($record), '#d8434d'); // red-400
    }
}
