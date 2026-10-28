<?php

namespace App\Filament\Widgets;

use App\Enums\StatusJogo;
use App\Enums\TipoEvento;
use App\Filament\Pages\Calendario;
use App\Models\Evento;
use App\Services\Painel\Indicadores;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

/** Próximos eventos da agenda (tela 16), de hoje em diante, com link para o mês no calendário. */
class Proximos extends Widget
{
    protected string $view = 'filament.painel.widgets.proximos';

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return Filament::auth()->user()?->can('viewAny', Evento::class) ?? false;
    }

    /** "Torneio · 20:00", "Torneio · 20:00 · rascunho", "Aluguel · 19:00 · Mesa 02", "Bloqueio · dia todo" */
    public static function detalhe(Evento $evento): string
    {
        $tipo = match (true) {
            $evento->jogo !== null => $evento->jogo->eTorneio() ? 'Torneio' : 'Cash game',
            default => $evento->tipo->label(),
        };

        return collect([
            $tipo,
            mb_strtolower($evento->horario()),
            $evento->tipo === TipoEvento::Jogo ? null : ($evento->mesas->isEmpty() ? null : $evento->nomesDasMesas()),
            $evento->jogo?->status === StatusJogo::Rascunho ? 'rascunho' : null,
        ])->filter()->join(' · ');
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        return [
            'eventos' => app(Indicadores::class)->proximosEventos(),
            'calendario' => Calendario::getUrl(),
        ];
    }

    public function urlDoMes(Evento $evento): string
    {
        return Calendario::getUrl(['mes' => $evento->data->format('Y-m')]);
    }
}
