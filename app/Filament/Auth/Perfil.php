<?php

namespace App\Filament\Auth;

use Filament\Auth\Pages\EditProfile;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * Perfil da conta do painel (menu do usuário → Perfil): o nome e a troca da própria senha, com a senha
 * atual. A conta não tem e-mail (o login é o `login`, que só muda em Segurança).
 */
class Perfil extends EditProfile
{
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getNameFormComponent(),
                TextInput::make('login')
                    ->label('Usuário')
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText('Para mudar o usuário, fale com quem cuida de Segurança.'),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
                $this->getCurrentPasswordFormComponent(),
            ]);
    }

    protected function getNameFormComponent(): Component
    {
        return TextInput::make('nome')
            ->label('Nome')
            ->required()
            ->maxLength(255);
    }

    protected function getCurrentPasswordFormComponent(): Component
    {
        // Sem e-mail na conta: a senha atual só é pedida para trocar a senha.
        return parent::getCurrentPasswordFormComponent()
            ->visible(fn (Get $get): bool => filled($get('password')));
    }
}
