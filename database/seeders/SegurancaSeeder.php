<?php

namespace Database\Seeders;

use App\Models\Seguranca;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Primeira conta do painel (grupo Desenvolvedor). Senha: PAINEL_SENHA_INICIAL do .env,
 * ou uma aleatória mostrada no terminal. Troque depois do primeiro acesso.
 */
class SegurancaSeeder extends Seeder
{
    public function run(): void
    {
        if (Seguranca::query()->where('login', 'dev')->exists()) {
            return;
        }

        $senha = env('PAINEL_SENHA_INICIAL') ?: Str::password(16, symbols: false);

        $conta = Seguranca::query()->create([
            'nome' => 'Desenvolvedor',
            'login' => 'dev',
            'password' => $senha,
            'ativo' => true,
        ]);

        $conta->assignRole('Desenvolvedor');

        if (! env('PAINEL_SENHA_INICIAL')) {
            $this->command?->warn("Conta do painel criada: login dev / senha {$senha}");
        }
    }
}
