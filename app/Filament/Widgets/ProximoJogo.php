<?php

namespace App\Filament\Widgets;

use App\Enums\FaseTorneio;
use App\Enums\StatusJogo;
use App\Filament\Resources\Jogos\JogoResource;
use App\Models\Jogo;
use App\Services\Jogo\Calculadora;
use App\Services\Jogo\Relogio;
use App\Services\Painel\Indicadores;
use App\Support\Dinheiro;
use App\Support\EtapaDoJogo;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

/**
 * Painel de controle, em destaque no topo: o jogo em andamento ou o próximo agendado, com os números da
 * noite e os botões para o cadastro e para o site. Agendado: confirmados, mesas, buy-in, blinds, prize pool
 * estimado (com `financeiro.ver`) e os rankings em que vale. Em andamento: em jogo, entradas, nível, mesas e,
 * com `financeiro.ver`, arrecadado e prize pool. Sem jogo agendado: Novo jogo e quantos estão em rascunho.
 */
class ProximoJogo extends Widget
{
    protected string $view = 'filament.painel.widgets.proximo-jogo';

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 2];

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return Filament::auth()->user()?->can('viewAny', Jogo::class) ?? false;
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $conta = Filament::auth()->user();
        $proximo = app(Indicadores::class)->proximoJogo()?->load('itens', 'rankings', 'estruturaBlinds', 'taxas', 'premiacoes');

        return [
            'proximo' => $proximo,
            'numeros' => $proximo ? $this->numeros($proximo) : [],
            'podeEditar' => $proximo && $conta->can('update', $proximo),
            'podeCriar' => $conta->can('create', Jogo::class),
            'rascunhos' => $proximo ? 0 : Jogo::query()->reais()->comStatus(StatusJogo::Rascunho)->count(),
        ];
    }

    /**
     * Os números da noite, como [rótulo, valor].
     *
     * @return list<array{0: string, 1: string}>
     */
    public function numeros(Jogo $jogo): array
    {
        $financeiro = Filament::auth()->user()->can('financeiro.ver');
        $calculadora = app(Calculadora::class);
        $ficha = $jogo->valor_ficha ? ['Valor da ficha', Dinheiro::brl($jogo->valor_ficha)] : null;

        if ($jogo->status === StatusJogo::EmAndamento) {
            $resumo = $calculadora->doJogo($jogo);

            $numeros = [
                ['Em jogo', $jogo->participantes()->naMesa()->count().' de '.$resumo->jogadores],
                ['Entradas', (string) $resumo->entradas],
                $jogo->eTorneio() ? $this->nivel($jogo) : $ficha,
                ['Mesas', (string) $jogo->mesas_count],
                $financeiro ? ['Arrecadado', Dinheiro::brlCentavos($resumo->arrecadacao)] : null,
                $financeiro ? ['Prize pool', Dinheiro::brlCentavos($resumo->prizePool)] : null,
            ];
        } else {
            $buyin = $jogo->buyinPadrao();
            $rankings = $jogo->rankings->pluck('nome')->join(', ', ' e ');

            $numeros = [
                ['Confirmados', (string) $jogo->participantes_count],
                ['Mesas', (string) $jogo->mesas_count],
                $buyin ? ['Buy-in', Dinheiro::brl($buyin->valor)] : null,
                $jogo->eTorneio() ? ['Blinds', $jogo->estruturaBlinds?->nome ?? 'sem estrutura'] : $ficha,
                $financeiro ? ['Prize pool estimado', Dinheiro::brlCentavos($calculadora->simular($jogo, $jogo->participantes_count)->prizePool)] : null,
                $rankings !== '' ? ['Vale ponto em', $rankings] : null,
            ];
        }

        return array_values(array_filter($numeros));
    }

    /**
     * Onde a noite está: os passos do status (e das fases, no torneio) e o índice do atual.
     *
     * @return array{0: list<string>, 1: int}
     */
    public static function etapas(Jogo $jogo): array
    {
        if ($jogo->eCashGame()) {
            return [['Agendado', 'Em andamento', 'Finalizado'], $jogo->status === StatusJogo::EmAndamento ? 1 : 0];
        }

        return [
            ['Agendado', 'Rebuys', 'Intervalo', 'Entradas fechadas', 'Finalizado'],
            $jogo->status === StatusJogo::EmAndamento ? match ($jogo->fase) {
                FaseTorneio::Intervalo => 2,
                FaseTorneio::SemEntradas => 3,
                default => 1,
            } : 0,
        ];
    }

    /** Nível do torneio agora: pelo relógio (sem gravar), ou o digitado na tela ao vivo. */
    private function nivel(Jogo $jogo): array
    {
        $estado = $jogo->temRelogio() ? app(Relogio::class)->estado($jogo) : null;

        if ($estado) {
            return $estado->noIntervalo()
                ? ['Nível', 'intervalo']
                : ['Nível '.$estado->numero, $estado->nivel()->blinds()];
        }

        return [$jogo->nivel_atual ? 'Nível '.$jogo->nivel_atual : 'Nível', $jogo->blinds_atual ?: '—'];
    }

    /** Editar no painel (ou ver, se não pode editar). */
    public function urlDoJogo(Jogo $jogo): string
    {
        return Filament::auth()->user()->can('update', $jogo)
            ? JogoResource::getUrl('edit', ['record' => $jogo])
            : JogoResource::getUrl('view', ['record' => $jogo]);
    }

    public function urlNoSite(Jogo $jogo): ?string
    {
        return EtapaDoJogo::url($jogo);
    }

    public function urlDosJogos(): string
    {
        return JogoResource::getUrl();
    }

    public function novoJogoUrl(): string
    {
        return JogoResource::getUrl('create');
    }
}
