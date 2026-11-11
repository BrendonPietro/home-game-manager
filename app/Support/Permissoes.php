<?php

namespace App\Support;

use App\Models\Permissao;
use App\Models\Seguranca;
use Database\Seeders\PermissoesSeeder;
use Illuminate\Support\Collection;

/**
 * Nomes das permissões para as telas (Segurança e Grupos) e a trava de quem dá permissão:
 * uma conta do painel só dá (ou mexe em quem tem) permissões que ela mesma tem.
 */
class Permissoes
{
    private const RECURSOS = [
        'jogos' => 'Jogos', 'modelos' => 'Modelos', 'estruturas' => 'Blinds', 'rankings' => 'Rankings', 'usuarios' => 'Usuários',
        'seguranca' => 'Contas do painel', 'grupos' => 'Grupos', 'mesas' => 'Mesas', 'eventos' => 'Agenda',
        'alugueis' => 'Aluguéis', 'temporadas' => 'Temporadas', 'acessos' => 'Usuários do site', 'relatorios' => 'Relatórios',
        'financeiro' => 'Financeiro', 'configuracoes' => 'Configurações', 'pagamentos' => 'Pagamentos',
    ];

    private const SITE = [
        'entradas.registrar' => 'Lançar entradas',
        'pagamentos.registrar' => 'Registrar pagamentos',
        'eliminacoes.registrar' => 'Registrar eliminações',
        'jogadores.confirmar' => 'Confirmar jogadores',
        'usuarios.cadastro_rapido' => 'Cadastro rápido',
        'mesas.organizar' => 'Montar as mesas',
        'jogo.fases' => 'Iniciar e mudar de fase',
        'relogio.controlar' => 'Controlar o relógio',
        'jogo.finalizar' => 'Finalizar o jogo',
        'acoes.desfazer' => 'Desfazer ações',
        'jogo.corrigir' => 'Corrigir a noite (voltar fase, desfazer depois de finalizar)',
        'estrutura.editar' => 'Alterar entradas e opcionais no jogo',
        'taxas.editar' => 'Alterar taxas no jogo',
        'financeiro.ver' => 'Ver o financeiro',
        'pagamentos.antecipado' => 'Marcar pagamento antecipado (fichas a mais)',
        'site.fora_da_noite' => 'Usar o site sem jogo em andamento',
    ];

    /** Ações do painel que não são ver/criar/editar/excluir. */
    private const ACOES = [
        'andamento' => 'editar em andamento',
        'antecipado' => 'marcar antecipado (fichas a mais)',
        'excluir_tudo' => 'excluir com lançamentos (apaga tudo)',
    ];

    /** "Jogos · ver", "Jogos · editar em andamento", "Lançar entradas" */
    public static function rotulo(string $nome, string $guard = 'painel'): string
    {
        if ($guard === 'web') {
            return self::SITE[$nome] ?? $nome;
        }

        [$recurso, $acao] = array_pad(explode('.', $nome, 2), 2, '');

        return (self::RECURSOS[$recurso] ?? $recurso).' · '.(self::ACOES[$acao] ?? $acao);
    }

    /**
     * Permissões de um guard na ordem das telas: no painel, recurso por recurso (ver, criar, editar,
     * excluir), o que monta uma grade de 4 colunas; depois relatórios, financeiro e configurações.
     *
     * @return array<string, string> nome => rótulo
     */
    public static function opcoes(string $guard): array
    {
        $ordem = $guard === 'web'
            ? array_keys(self::SITE)
            : [
                ...collect(PermissoesSeeder::RECURSOS_PAINEL)->crossJoin(PermissoesSeeder::ACOES_PAINEL)->map(fn (array $par) => implode('.', $par))->all(),
                ...PermissoesSeeder::EXTRAS_PAINEL,
            ];

        $existentes = Permissao::query()->where('guard_name', $guard)->pluck('name');

        return collect($ordem)
            ->intersect($existentes)
            ->merge($existentes->diff($ordem)->sort()) // criada fora do seeder vai para o fim
            ->mapWithKeys(fn (string $nome): array => [$nome => self::rotulo($nome, $guard)])
            ->all();
    }

    /** A conta tem todas estas permissões do painel? */
    public static function abrange(Seguranca $conta, iterable $permissoes): bool
    {
        $minhas = $conta->getAllPermissions()->pluck('name');

        return collect($permissoes)
            ->map(fn ($permissao): string => is_string($permissao) ? $permissao : $permissao->name)
            ->diff($minhas)
            ->isEmpty();
    }

    /** @return Collection<int, string> permissões que a conta tem e outra conta não */
    public static function faltando(Seguranca $conta, iterable $permissoes): Collection
    {
        return collect($permissoes)
            ->map(fn ($permissao): string => is_string($permissao) ? $permissao : $permissao->name)
            ->diff($conta->getAllPermissions()->pluck('name'))
            ->values();
    }
}
