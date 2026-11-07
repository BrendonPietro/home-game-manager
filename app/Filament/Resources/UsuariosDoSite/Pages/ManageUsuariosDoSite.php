<?php

namespace App\Filament\Resources\UsuariosDoSite\Pages;

use App\Filament\Resources\UsuariosDoSite\UsuarioDoSiteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageUsuariosDoSite extends ManageRecords
{
    protected static string $resource = UsuarioDoSiteResource::class;

    protected static ?string $title = 'Usuários do site';

    protected function getHeaderActions(): array
    {
        return [
            UsuarioDoSiteResource::acaoDarAcesso(),
            // Pessoa nova, que entra no site (e aparece também em Jogadores, se for jogar).
            CreateAction::make()
                ->label('Novo usuário do site')
                ->mutateDataUsing(fn (array $data): array => [...$data, 'acessa_site' => true]),
        ];
    }
}
