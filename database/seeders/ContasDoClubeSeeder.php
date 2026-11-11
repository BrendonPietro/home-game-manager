<?php

namespace Database\Seeders;

use App\Models\Seguranca;
use App\Models\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Contas de quem administra o clube (config/clube.php, do .env), em qualquer ambiente: cada pessoa
 * ganha o login do site (usuarios) e o do painel (seguranca), com o mesmo usuário e a mesma senha
 * inicial. O usuário pode ser um nome ("FulanoDeTal", entra assim nos dois) ou um e-mail (no site
 * vai para o e-mail). Desenvolvedor fica no grupo Desenvolvedor dos dois lados; os admins, no Admin.
 * Sem senha no .env, uma aleatória aparece no terminal. Não mexe em conta que já existe (a senha
 * trocada depois de entrar continua).
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

        foreach ($desenvolvedores as [$nome, $login]) {
            $this->criar($nome, $login, config('clube.desenvolvedor_senha'), 'CONTA_DESENVOLVEDOR_SENHA', 'Desenvolvedor');
        }

        foreach ($admins as [$nome, $login]) {
            $this->criar($nome, $login, config('clube.admins_senha'), 'CONTAS_ADMIN_SENHA', 'Admin');
        }
    }

    /**
     * "Fulano de Tal <FulanoDeTal>, Seij <seij@exemplo.com>" → [['Fulano de Tal', 'fulanodetal'], ['Seij', 'seij@exemplo.com']].
     * O usuário vai em minúsculas (o login dos dois lados não diferencia). Sem o nome, o nome é o usuário
     * (ou o começo do e-mail). O que não for usuário nem e-mail válido fica de fora.
     *
     * @return list<array{string, string}>
     */
    public static function ler(?string $lista): array
    {
        $contas = [];

        foreach (array_filter(array_map('trim', explode(',', (string) $lista))) as $conta) {
            if (preg_match('/^(.*?)\s*<([^>]+)>$/', $conta, $partes)) {
                [$nome, $login] = [trim($partes[1]), trim($partes[2])];
            } else {
                [$nome, $login] = ['', $conta];
            }

            $eEmail = str_contains($login, '@');
            $valido = $eEmail ? filter_var($login, FILTER_VALIDATE_EMAIL) : preg_match('/^[A-Za-z0-9._-]{1,60}$/', $login);

            if ($valido) {
                $contas[] = [$nome !== '' ? $nome : Str::ucfirst($eEmail ? Str::before($login, '@') : $login), Str::lower($login)];
            }
        }

        return $contas;
    }

    private function criar(string $nome, string $login, ?string $doEnv, string $variavel, string $grupo): void
    {
        $senha = filled($doEnv) ? $doEnv : Str::password(16, symbols: false);
        $campoDoSite = str_contains($login, '@') ? 'email' : 'login';
        $criadas = [];

        if (! Usuario::withTrashed()->where($campoDoSite, $login)->exists()) {
            Usuario::query()->create([
                'nome' => $nome,
                $campoDoSite => $login,
                'password' => $senha,
                'acessa_site' => true,
                'ativo' => true,
            ])->assignRole($grupo);

            $criadas[] = 'site';
        }

        if (! Seguranca::query()->where('login', $login)->exists()) {
            Seguranca::query()->create([
                'nome' => $nome,
                'login' => $login,
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
            ? $this->command?->info("Conta do clube: {$login} ({$grupo}, {$onde}), senha do {$variavel}")
            : $this->command?->warn("Conta do clube: {$login} ({$grupo}, {$onde}) / senha {$senha}");
    }
}
