<?php

namespace Database\Seeders;

use App\Models\Grupo;
use App\Models\Permissao;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permissões `recurso.acao` e grupos iniciais dos dois guards.
 * Pode rodar de novo: cria o que falta e ressincroniza os grupos padrão.
 */
class PermissoesSeeder extends Seeder
{
    public const RECURSOS_PAINEL = [
        'jogos', 'modelos', 'rankings', 'usuarios', 'seguranca',
        'grupos', 'mesas', 'eventos', 'alugueis', 'temporadas',
    ];

    public const ACOES_PAINEL = ['ver', 'criar', 'editar', 'excluir'];

    public const EXTRAS_PAINEL = [
        'relatorios.ver', 'relatorios.exportar', 'financeiro.ver', 'configuracoes.editar',
    ];

    public const SITE_OPERADOR = [
        'entradas.registrar',
        'pagamentos.registrar',
        'eliminacoes.registrar',
        'jogadores.confirmar',
        'usuarios.cadastro_rapido',
    ];

    public const SITE_ADMIN = [
        'mesas.organizar',
        'jogo.fases',
        'jogo.finalizar',
        'acoes.desfazer',
        'estrutura.editar', // entradas e opcionais durante o jogo
        'taxas.editar',
        'financeiro.ver',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // ── Painel ──
        $painel = [];
        foreach (self::RECURSOS_PAINEL as $recurso) {
            foreach (self::ACOES_PAINEL as $acao) {
                $painel[] = "{$recurso}.{$acao}";
            }
        }
        $painel = array_merge($painel, self::EXTRAS_PAINEL);
        $this->criar($painel, 'painel');

        $this->grupo('Desenvolvedor', 'painel', $painel);

        $this->grupo('Admin', 'painel', array_values(array_filter(
            $painel,
            fn (string $p) => ! str_starts_with($p, 'grupos.'),
        )));

        $this->grupo('Geral', 'painel', [
            'jogos.ver', 'eventos.ver', 'rankings.ver', 'usuarios.ver', 'usuarios.criar',
        ]);

        // ── Site ──
        $this->criar([...self::SITE_OPERADOR, ...self::SITE_ADMIN], 'web');

        $this->grupo('Operador', 'web', self::SITE_OPERADOR);
        $this->grupo('Admin', 'web', [...self::SITE_OPERADOR, ...self::SITE_ADMIN]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** @param list<string> $nomes */
    private function criar(array $nomes, string $guard): void
    {
        foreach ($nomes as $nome) {
            Permissao::findOrCreate($nome, $guard);
        }
    }

    /** @param list<string> $permissoes */
    private function grupo(string $nome, string $guard, array $permissoes): void
    {
        Grupo::findOrCreate($nome, $guard)->syncPermissions($permissoes);
    }
}
