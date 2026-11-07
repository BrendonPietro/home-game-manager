<?php

namespace App\Services\Painel;

use App\Enums\StatusAluguel;
use App\Enums\StatusJogo;
use App\Filament\Pages\Calendario;
use App\Filament\Pages\Configuracoes;
use App\Filament\Pages\Metricas;
use App\Filament\Pages\Relatorios;
use App\Filament\Resources\Alugueis\AluguelResource;
use App\Filament\Resources\EstruturasBlinds\EstruturaBlindsResource;
use App\Filament\Resources\Grupos\GrupoResource;
use App\Filament\Resources\Jogos\JogoResource;
use App\Filament\Resources\Mesas\MesaResource;
use App\Filament\Resources\Modelos\ModeloResource;
use App\Filament\Resources\Rankings\RankingResource;
use App\Filament\Resources\Segurancas\SegurancaResource;
use App\Filament\Resources\Temporadas\TemporadaResource;
use App\Filament\Resources\Usuarios\UsuarioResource;
use App\Filament\Resources\UsuariosDoSite\UsuarioDoSiteResource;
use App\Models\Aluguel;
use App\Models\EstruturaBlinds;
use App\Models\Evento;
use App\Models\Grupo;
use App\Models\Jogo;
use App\Models\Mesa;
use App\Models\Ranking;
use App\Models\Seguranca;
use App\Models\Temporada;
use App\Models\Usuario;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Illuminate\Database\Eloquent\Builder;

/**
 * Atalhos do Painel de controle: os módulos do menu (Cadastros, Agenda, Dashboards e relatórios,
 * Sistema), só com o que a conta pode abrir, na ordem do menu. Cada item traz uma linha de contexto
 * ("1 agendado · 2 em rascunho", "24 ativos"). Lê os resources e as páginas do próprio painel, então
 * um cadastro novo entra sozinho (sem linha de contexto até ganhar uma aqui).
 */
class Atalhos
{
    /**
     * @return list<array{grupo: string, itens: list<array{rotulo: string, icone: mixed, url: string, contexto: ?string}>}>
     */
    public function modulos(): array
    {
        $painel = Filament::getCurrentOrDefaultPanel();
        $ordemDosGrupos = array_values(array_map(
            fn (NavigationGroup|string $grupo): string => $grupo instanceof NavigationGroup ? (string) $grupo->getLabel() : $grupo,
            $painel->getNavigationGroups(),
        ));

        return collect([...$painel->getResources(), ...$painel->getPages()])
            ->unique()
            ->filter(fn (string $classe): bool => filled($classe::getNavigationGroup())
                && $classe::shouldRegisterNavigation()
                && $classe::canAccess())
            ->sortBy(fn (string $classe): array => [$classe::getNavigationSort() ?? 999, $classe::getNavigationLabel()])
            ->groupBy(fn (string $classe): string => (string) $classe::getNavigationGroup())
            ->sortBy(fn ($itens, string $grupo): int => ($posicao = array_search($grupo, $ordemDosGrupos, true)) === false ? 999 : $posicao)
            ->map(fn ($itens, string $grupo): array => [
                'grupo' => $grupo,
                'itens' => $itens->map(fn (string $classe): array => [
                    'rotulo' => $classe::getNavigationLabel(),
                    'icone' => $classe::getNavigationIcon(),
                    'url' => $classe::getUrl(),
                    'contexto' => $this->contexto($classe),
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    /** A linha de contexto de cada atalho (contagens baratas, uma consulta cada). */
    public function contexto(string $classe): ?string
    {
        $n = fn (int $quantos, string $um, string $varios): string => $quantos === 1 ? "1 {$um}" : "{$quantos} {$varios}";

        return match ($classe) {
            JogoResource::class => $this->jogos($n),
            ModeloResource::class => $n(Jogo::query()->modelos()->count(), 'modelo', 'modelos'),
            UsuarioResource::class => $n(Usuario::query()->ativos()->count(), 'ativo', 'ativos'),
            MesaResource::class => $this->mesas(),
            EstruturaBlindsResource::class => $n(EstruturaBlinds::query()->count(), 'estrutura', 'estruturas'),
            RankingResource::class => $n(Ranking::query()->valendoHoje()->count(), 'valendo agora', 'valendo agora'),
            TemporadaResource::class => Temporada::query()->whereDate('inicio', '<=', today())->whereDate('fim', '>=', today())->value('nome')
                ?? 'nenhuma em andamento',
            Calendario::class => ($eventos = $this->eventosDaquiPraFrente()) ? $n($eventos, 'evento marcado', 'eventos marcados') : 'nada marcado',
            AluguelResource::class => ($abertos = Aluguel::query()->whereIn('status', [StatusAluguel::Reservado, StatusAluguel::Confirmado])->count())
                ? $n($abertos, 'em aberto', 'em aberto') : 'nenhum em aberto',
            Metricas::class => 'médias e comparações',
            Relatorios::class => 'jogos, premiações e arrecadação',
            Configuracoes::class => 'jogo novo e premiação',
            UsuarioDoSiteResource::class => $n(Usuario::query()->where('acessa_site', true)->count(), 'com acesso', 'com acesso'),
            SegurancaResource::class => $n(Seguranca::query()->count(), 'conta', 'contas'),
            GrupoResource::class => $n(Grupo::query()->count(), 'grupo', 'grupos'),
            default => null,
        };
    }

    /** "1 agendado · 2 em rascunho", "em andamento", "nenhum agendado". */
    private function jogos(callable $n): string
    {
        $porStatus = Jogo::query()->reais()
            ->comStatus(StatusJogo::Rascunho, StatusJogo::Agendado, StatusJogo::EmAndamento)
            ->selectRaw('status, count(*) as quantos')
            ->groupBy('status')
            ->pluck('quantos', 'status');
        $quantos = fn (StatusJogo $status): int => (int) ($porStatus[$status->value] ?? 0);

        return collect([
            $quantos(StatusJogo::EmAndamento) ? $n($quantos(StatusJogo::EmAndamento), 'em andamento', 'em andamento') : null,
            $quantos(StatusJogo::Agendado) ? $n($quantos(StatusJogo::Agendado), 'agendado', 'agendados') : null,
            $quantos(StatusJogo::Rascunho) ? $n($quantos(StatusJogo::Rascunho), 'em rascunho', 'em rascunho') : null,
        ])->filter()->join(' · ') ?: 'nenhum agendado';
    }

    /** "2 de 3 ativas", "todas ativas", "nenhuma cadastrada". */
    private function mesas(): string
    {
        $mesas = Mesa::query()->pluck('ativa');
        $ativas = $mesas->filter()->count();

        return match (true) {
            $mesas->isEmpty() => 'nenhuma cadastrada',
            $ativas === $mesas->count() => $mesas->count() === 1 ? '1 ativa' : "todas as {$ativas} ativas",
            default => "{$ativas} de {$mesas->count()} ativas",
        };
    }

    private function eventosDaquiPraFrente(): int
    {
        return Evento::query()
            ->whereDate('data', '>=', today())
            ->whereDoesntHave('aluguel', fn (Builder $q) => $q->where('status', StatusAluguel::Cancelado))
            ->count();
    }
}
