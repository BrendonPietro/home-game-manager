<?php

namespace Database\Seeders;

use App\Models\Seguranca;
use App\Models\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Contas para testar os acessos (docs/roteiro-de-teste.md), só no ambiente local: no site o Operador
 * (o celular da noite) e no painel o Geral. Mesma senha das contas dev de cada lado
 * (SITE_SENHA_INICIAL e PAINEL_SENHA_INICIAL do .env), ou uma aleatória mostrada no terminal.
 * Não mexe em conta que já existe.
 */
class ContasDeTesteSeeder extends Seeder
{
    public const SITE = [
        'operador@rodrigues.test' => ['nome' => 'Operador (teste)', 'grupo' => 'Operador'],
    ];

    public const PAINEL = [
        'geral' => ['nome' => 'Geral (teste)', 'grupo' => 'Geral'],
    ];

    public function run(): void
    {
        foreach (self::SITE as $email => $conta) {
            if (Usuario::withTrashed()->where('email', $email)->exists()) {
                continue;
            }

            $senha = env('SITE_SENHA_INICIAL') ?: Str::password(16, symbols: false);

            Usuario::query()->create([
                'nome' => $conta['nome'],
                'email' => $email,
                'password' => $senha,
                'acessa_site' => true,
                'ativo' => true,
            ])->assignRole($conta['grupo']);

            $this->avisar("site {$email} ({$conta['grupo']})", 'SITE_SENHA_INICIAL', $senha);
        }

        foreach (self::PAINEL as $login => $conta) {
            if (Seguranca::query()->where('login', $login)->exists()) {
                continue;
            }

            $senha = env('PAINEL_SENHA_INICIAL') ?: Str::password(16, symbols: false);

            Seguranca::query()->create([
                'nome' => $conta['nome'],
                'login' => $login,
                'password' => $senha,
                'ativo' => true,
            ])->assignRole($conta['grupo']);

            $this->avisar("painel {$login} ({$conta['grupo']})", 'PAINEL_SENHA_INICIAL', $senha);
        }
    }

    private function avisar(string $conta, string $variavel, string $senha): void
    {
        env($variavel)
            ? $this->command?->info("Conta criada: {$conta}, senha do {$variavel}")
            : $this->command?->warn("Conta criada: {$conta} / senha {$senha}");
    }
}
