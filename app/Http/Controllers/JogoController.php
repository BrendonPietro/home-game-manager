<?php

namespace App\Http\Controllers;

use App\Enums\MetodoPremiacao;
use App\Enums\StatusJogo;
use App\Models\Jogo;
use App\Models\RegraPremiacao;
use App\Services\Jogo\Calculadora;
use App\Support\EtapaDoJogo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Telas do jogo no site: 03 (escolher o jogo) e 04 (conferência); as outras só abrem o Livewire da etapa. */
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
        $jogo->load(['itens', 'taxas', 'premiacoes', 'mesas.mesa', 'mesas.dealer', 'floor', 'temporada', 'estruturaBlinds.niveis'])
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

    /** Telas 07–09 e 11: a noite ao vivo (Livewire Jogo\AoVivo, de ponta a ponta). */
    public function aoVivo(Jogo $jogo): View
    {
        return view('site.pages.ao-vivo', ['jogo' => $jogo]);
    }

    /** Tela 10: resumo ao vivo (Livewire Jogo\Resumo). */
    public function resumo(Jogo $jogo): View
    {
        return view('site.pages.resumo', ['jogo' => $jogo]);
    }

    /** v3: tela do relógio de quem controla o torneio (Livewire Jogo\ControleDoRelogio). */
    public function relogio(Jogo $jogo): View
    {
        return view('site.pages.relogio-controle', ['jogo' => $jogo]);
    }

    /** v3: a TV é o link do relógio do torneio (sem login, só o relógio); o endereço antigo leva para ele. */
    public function tv(Jogo $jogo): RedirectResponse
    {
        if ($jogo->e_modelo || ! in_array($jogo->status, EtapaDoJogo::STATUS_NO_SITE, true)) {
            return redirect()->route('jogos.index')->with('aviso', "{$jogo->nome} não está disponível no site.");
        }

        return redirect()->away($jogo->linkDoRelogio());
    }

    /** Tela 14: fechamento financeiro e Finalizar (Livewire Jogo\Fechamento). */
    public function fechamento(Jogo $jogo): View
    {
        return view('site.pages.fechamento', ['jogo' => $jogo]);
    }

    /** Tela 13: resultado e posições (Livewire Jogo\Resultado). */
    public function resultado(Jogo $jogo): View
    {
        return view('site.pages.resultado', ['jogo' => $jogo]);
    }

    /** Tela 12: do arrecadado ao prize pool e a distribuição gravada ao fechar as entradas (só torneio). */
    public function premiacao(Jogo $jogo, Calculadora $calculadora): View
    {
        $jogo->load(['taxas', 'premiacoes']);

        return view('site.pages.premiacao', [
            'jogo' => $jogo,
            'resumo' => $calculadora->doJogo($jogo),
            'naPosicao' => $jogo->participantes()->with('usuario')->whereNotNull('posicao_final')->get()->keyBy('posicao_final'),
            'regras' => $jogo->metodo_premiacao === MetodoPremiacao::Regra
                ? RegraPremiacao::query()->orderBy('jogadores_min')->get()
                : collect(),
        ]);
    }

    /** O jogo ao vivo; se não houver, o agendado para hoje. */
    private function destaque(): ?Jogo
    {
        $jogo = Jogo::query()->reais()->comStatus(StatusJogo::EmAndamento)->orderBy('data')->first()
            ?? Jogo::query()->reais()->comStatus(StatusJogo::Agendado)->whereDate('data', today())->orderBy('horario')->first();

        return $jogo?->loadCount(['participantes', 'mesas'])->load(['itens', 'taxas', 'temporada']);
    }
}
