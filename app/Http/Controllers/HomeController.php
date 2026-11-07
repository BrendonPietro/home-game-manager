<?php

namespace App\Http\Controllers;

use App\Enums\StatusJogo;
use App\Models\Evento;
use App\Models\Jogo;
use App\Models\Usuario;
use App\Services\Jogo\Calculadora;
use App\Services\Jogo\Relogio;
use App\Support\JanelaDoSite;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Tela 02: dashboard do site. Próximo jogo (ou o que está ao vivo), números, últimos eventos,
 * agenda e a última noite.
 */
class HomeController extends Controller
{
    /** Fora da janela do torneio (quem não é admin): continua logado e vê quando o próximo abre. */
    public function semTorneio(): View
    {
        $proximo = JanelaDoSite::proximo();

        return view('site.pages.sem-torneio', [
            'proximo' => $proximo,
            'abre' => $proximo ? JanelaDoSite::abre($proximo) : null,
        ]);
    }

    public function index(Calculadora $calculadora, Relogio $relogio): View
    {
        $proximo = $this->proximoJogo();
        $resumoDoProximo = null;

        if ($proximo) {
            $proximo = $relogio->sincronizar($proximo); // ao vivo com relógio: o nível de agora
            $proximo->loadCount(['participantes', 'mesas'])->load(['itens', 'taxas', 'premiacoes', 'temporada']);

            // Ao vivo: números reais. Agendado: estimativa com os confirmados e o buy-in padrão.
            $resumoDoProximo = $proximo->status === StatusJogo::EmAndamento
                ? $calculadora->doJogo($proximo)
                : $calculadora->simular($proximo, $proximo->participantes_count);
        }

        $ultimos = Jogo::query()->reais()->comStatus(StatusJogo::Finalizado)
            ->orderByDesc('data')
            ->with('campeao.usuario')
            ->withCount(['participantes', 'mesas'])
            ->withSum(['entradas as arrecadacao' => fn (Builder $q) => $q->whereNull('cancelada_em')], 'valor')
            ->limit(5)
            ->get();

        $ultimaNoite = $ultimos->first();

        return view('site.pages.home', [
            'proximo' => $proximo,
            'resumoDoProximo' => $resumoDoProximo,
            'numeros' => $this->numeros(),
            'ultimos' => $ultimos,
            'agenda' => $this->agenda(),
            'ultimaNoite' => $ultimaNoite,
            'resumoDaUltima' => $ultimaNoite ? $calculadora->doJogo($ultimaNoite) : null,
        ]);
    }

    /** O jogo ao vivo; se não houver, o próximo agendado a partir de hoje. */
    private function proximoJogo(): ?Jogo
    {
        return Jogo::query()->reais()->comStatus(StatusJogo::EmAndamento)->orderBy('data')->first()
            ?? Jogo::query()->reais()->comStatus(StatusJogo::Agendado)
                ->whereDate('data', '>=', today())
                ->orderBy('data')
                ->orderBy('horario')
                ->first();
    }

    /** @return array{emAndamento: int, proximos: int, ateData: ?Carbon, jogadores: int, media: ?float, mediaDe: int} */
    private function numeros(): array
    {
        $proximos = Jogo::query()->reais()->comStatus(StatusJogo::Agendado)->whereDate('data', '>=', today());

        $ultimasNoites = Jogo::query()->reais()->comStatus(StatusJogo::Finalizado)
            ->orderByDesc('data')
            ->withCount('participantes')
            ->limit(8)
            ->get();

        $ateData = (clone $proximos)->max('data');

        return [
            'emAndamento' => Jogo::query()->reais()->comStatus(StatusJogo::EmAndamento)->count(),
            'proximos' => (clone $proximos)->count(),
            'ateData' => $ateData ? Carbon::parse($ateData) : null,
            'jogadores' => Usuario::query()->ativos()->count(),
            'media' => $ultimasNoites->isNotEmpty() ? $ultimasNoites->avg('participantes_count') : null,
            'mediaDe' => $ultimasNoites->count(),
        ];
    }

    /**
     * Próximos eventos da agenda; jogo em rascunho ou cancelado não aparece.
     *
     * @return Collection<int, Evento>
     */
    private function agenda(): Collection
    {
        return Evento::query()
            ->whereDate('data', '>=', today())
            ->where(fn (Builder $q) => $q->whereNull('jogo_id')
                ->orWhereHas('jogo', fn (Builder $jogo) => $jogo->comStatus(StatusJogo::Agendado, StatusJogo::EmAndamento)))
            ->orderBy('data')
            ->orderBy('hora_inicio')
            ->with(['jogo', 'mesas'])
            ->limit(4)
            ->get();
    }
}
