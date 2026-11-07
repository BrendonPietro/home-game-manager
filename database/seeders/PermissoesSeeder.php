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
        'jogos', 'modelos', 'estruturas', 'rankings', 'usuarios', 'seguranca',
        'grupos', 'mesas', 'eventos', 'alugueis', 'temporadas',
        'acessos', // Sistema → Usuários do site: dar e tirar o acesso ao site
    ];

    public const ACOES_PAINEL = ['ver', 'criar', 'editar', 'excluir'];

    public const EXTRAS_PAINEL = [
        'relatorios.ver', 'relatorios.exportar', 'financeiro.ver', 'configuracoes.editar',
        'pagamentos.registrar', // pagamento antecipado no pré-torneio (lista de confirmados do jogo)
        'jogos.andamento', // mudar o cadastro com o jogo em andamento (refaz entradas e prêmios: RefazerPeloCadastro)
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
        'relogio.controlar', // v2: relógio do torneio com estrutura de blinds
        'jogo.finalizar',
        'acoes.desfazer',
        'jogo.corrigir', // voltar fase e desfazer pagamento/acerto depois de finalizar (fica no histórico)
        'estrutura.editar', // entradas e opcionais durante o jogo
        'taxas.editar',
        'financeiro.ver',
        'site.fora_da_noite', // sem ela (Operador), o site só abre com um jogo em andamento
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
            'jogos.ver', 'estruturas.ver', 'eventos.ver', 'rankings.ver', 'usuarios.ver', 'usuarios.criar',
        ]);

        // ── Site ──
        $this->criar([...self::SITE_OPERADOR, ...self::SITE_ADMIN], 'web');

        $this->grupo('Operador', 'web', self::SITE_OPERADOR);
        $this->grupo('Admin', 'web', [...self::SITE_OPERADOR, ...self::SITE_ADMIN]);
        // Como no painel: o Desenvolvedor tem tudo do site, mesmo que o Admin perca alguma permissão em Grupos.
        $this->grupo('Desenvolvedor', 'web', [...self::SITE_OPERADOR, ...self::SITE_ADMIN]);

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
