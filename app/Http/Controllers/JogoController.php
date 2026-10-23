<?php

namespace App\Http\Controllers;

use App\Enums\StatusJogo;
use App\Models\Jogo;
use App\Services\Jogo\Calculadora;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Telas do jogo no site que não são Livewire: 03 (escolher o jogo) e 04 (conferência). */
class JogoController extends Controller
{
    /** aba => status, na ordem das abas */
    private const ABAS = [
        'proximos' => StatusJogo::Agendado,
        'andamento' => StatusJogo::EmAndamento,
        'finalizados' => StatusJogo::Finalizado,
    ];

    public function index(Request $request): View
    {
        $aba = array_key_exists($request->query('aba'), self::ABAS) ? $request->query('aba') : 'proximos';
        $busca = trim((string) $request->query('busca'));

        $jogos = Jogo::query()->reais()
            ->comStatus(self::ABAS[$aba])
            ->when($busca !== '', fn (Builder $q) => $q->where('nome', 'like', '%'.addcslashes($busca, '%_\\').'%'))
            ->when($aba === 'finalizados', fn (Builder $q) => $q->orderByDesc('data'), fn (Builder $q) => $q->orderBy('data')->orderBy('horario'))
            ->withCount(['participantes', 'mesas'])
            ->with('itens')
            ->paginate(15)
            ->withQueryString();

        $contagem = collect(self::ABAS)->map(fn (StatusJogo $status) => Jogo::query()->reais()->comStatus($status)->count());

        return view('site.pages.jogos', [
            'aba' => $aba,
            'busca' => $busca,
            'jogos' => $jogos,
            'contagem' => $contagem,
            'destaque' => $this->destaque(),
        ]);
    }

    /** Tela 04: conferir com o grupo o que foi cadastrado no painel antes de começar. */
    public function conferencia(Jogo $jogo, Calculadora $calculadora): View
    {
        $jogo->load(['itens', 'taxas', 'premiacoes', 'mesas.mesa', 'mesas.dealer', 'floor', 'temporada'])
            ->loadCount('participantes');

        return view('site.pages.conferencia', [
            'jogo' => $jogo,
            'estimado' => $calculadora->simular($jogo, $jogo->participantes_count),
        ]);
    }

    /** Tela 05: quem vai jogar (Livewire Jogo\Jogadores dentro do layout da noite). */
    public function jogadores(Jogo $jogo): View
    {
        return view('site.pages.jogadores', ['jogo' => $jogo]);
    }

    /** Tela 06: montar as mesas e iniciar (Livewire Jogo\Mesas dentro do layout da noite). */
    public function mesas(Jogo $jogo): View
    {
        return view('site.pages.mesas', ['jogo' => $jogo]);
    }

    /** O jogo ao vivo; se não houver, o agendado para hoje. */
    private function destaque(): ?Jogo
    {
        $jogo = Jogo::query()->reais()->comStatus(StatusJogo::EmAndamento)->orderBy('data')->first()
            ?? Jogo::query()->reais()->comStatus(StatusJogo::Agendado)->whereDate('data', today())->orderBy('horario')->first();

        return $jogo?->loadCount(['participantes', 'mesas'])->load(['itens', 'taxas', 'temporada']);
    }
}
