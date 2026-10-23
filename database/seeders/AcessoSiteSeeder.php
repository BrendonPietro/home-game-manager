<?php

namespace Database\Seeders;

use App\Models\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Primeira conta do site (grupo Admin do guard web), para entrar antes de existir o cadastro de usuários
 * no painel. E-mail dev@rodrigues.test; senha: SITE_SENHA_INICIAL do .env, ou uma aleatória mostrada no
 * terminal. Desative ou troque depois do primeiro acesso.
 */
class AcessoSiteSeeder extends Seeder
{
    public const EMAIL = 'dev@rodrigues.test';

    public function run(): void
    {
        if (Usuario::withTrashed()->where('email', self::EMAIL)->exists()) {
            return;
        }

        $senha = env('SITE_SENHA_INICIAL') ?: Str::password(16, symbols: false);

        $usuario = Usuario::query()->create([
            'nome' => 'Desenvolvedor',
            'email' => self::EMAIL,
            'password' => $senha,
            'acessa_site' => true,
            'ativo' => true,
        ]);

        $usuario->assignRole('Admin');

        if (! env('SITE_SENHA_INICIAL')) {
            $this->command?->warn('Conta do site criada: '.self::EMAIL." / senha {$senha}");
        }
    }
}
