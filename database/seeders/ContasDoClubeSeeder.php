<?php

namespace Database\Seeders;

use App\Models\Seguranca;
use App\Models\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Contas de quem administra o clube (config/clube.php, do .env), em qualquer ambiente: cada pessoa
 * ganha o login do site (usuarios, pelo e-mail) e o do painel (seguranca, com o e-mail como login),
 * com a mesma senha inicial. Desenvolvedor fica no grupo Desenvolvedor dos dois lados; os admins, no
 * Admin. Sem senha no .env, uma aleatória aparece no terminal. Não mexe em conta que já existe
 * (a senha trocada depois de entrar continua).
 */
class ContasDoClubeSeeder extends Seeder
{
    public function run(): void
    {
        $desenvolvedores = self::ler(config('clube.desenvolvedor'));
        $admins = self::ler(config('clube.admins'));

        if ($desenvolvedores === [] && $admins === []) {
            $this->command?->warn('Contas do clube: CONTA_DESENVOLVEDOR e CONTAS_ADMIN vazias no .env, nenhuma criada.');

            return;
        }

        foreach ($desenvolvedores as [$nome, $email]) {
            $this->criar($nome, $email, config('clube.desenvolvedor_senha'), 'CONTA_DESENVOLVEDOR_SENHA', 'Desenvolvedor');
        }

        foreach ($admins as [$nome, $email]) {
            $this->criar($nome, $email, config('clube.admins_senha'), 'CONTAS_ADMIN_SENHA', 'Admin');
        }
    }

    /**
     * "Brendon <brendon@exemplo.com>, Seij <seij@exemplo.com>" → [['Brendon', 'brendon@exemplo.com'], …].
     * Sem o nome ("seij@exemplo.com"), o nome é o começo do e-mail.
     *
     * @return list<array{string, string}>
     */
    public static function ler(?string $lista): array
    {
        $contas = [];

        foreach (array_filter(array_map('trim', explode(',', (string) $lista))) as $conta) {
            if (preg_match('/^(.*?)\s*<([^>]+)>$/', $conta, $partes)) {
                [$nome, $email] = [trim($partes[1]), trim($partes[2])];
            } else {
                [$nome, $email] = ['', $conta];
            }

            $email = Str::lower($email);

            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $contas[] = [$nome !== '' ? $nome : Str::ucfirst(Str::before($email, '@')), $email];
            }
        }

        return $contas;
    }

    private function criar(string $nome, string $email, ?string $doEnv, string $variavel, string $grupo): void
    {
        $senha = filled($doEnv) ? $doEnv : Str::password(16, symbols: false);
        $criadas = [];

        if (! Usuario::withTrashed()->where('email', $email)->exists()) {
            Usuario::query()->create([
                'nome' => $nome,
                'email' => $email,
                'password' => $senha,
                'acessa_site' => true,
                'ativo' => true,
            ])->assignRole($grupo);

            $criadas[] = 'site';
        }

        if (! Seguranca::query()->where('login', $email)->exists()) {
            Seguranca::query()->create([
                'nome' => $nome,
                'login' => $email,
                'password' => $senha,
                'ativo' => true,
            ])->assignRole($grupo);

            $criadas[] = 'painel';
        }

        if ($criadas === []) {
            return;
        }

        $onde = implode(' e ', $criadas);

        filled($doEnv)
            ? $this->command?->info("Conta do clube: {$email} ({$grupo}, {$onde}), senha do {$variavel}")
            : $this->command?->warn("Conta do clube: {$email} ({$grupo}, {$onde}) / senha {$senha}");
    }
}
