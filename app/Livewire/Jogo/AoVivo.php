<?php

namespace App\Livewire\Jogo;

use App\Actions\Jogo\AbrirIntervalo;
use App\Actions\Jogo\AtualizarNivel;
use App\Actions\Jogo\ControlarRelogio;
use App\Actions\Jogo\FecharEntradas;
use App\Actions\Jogo\TrocarLinkDoRelogio;
use App\Actions\Jogo\TrocarLugar;
use App\Actions\Jogo\VoltarFase;
use App\Enums\ComandoRelogio;
use App\Enums\FaseTorneio;
use App\Enums\StatusParticipante;
use App\Enums\TipoItem;
use App\Exceptions\RegraDoJogo;
use App\Livewire\Jogo\Concerns\EscolheQuemSenta;
use App\Models\Entrada;
use App\Models\Jogo;
use App\Models\JogoMesa;
use App\Models\Participante;
use App\Models\Usuario;
use App\Services\Jogo\Calculadora;
use App\Services\Jogo\Entradas;
use App\Services\Jogo\EntradasDoJogador;
use App\Services\Jogo\Relogio;
use App\Support\Dinheiro;
use App\Support\EtapaDoJogo;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Telas 07 e 07m: a noite ao vivo. Faixa com nível, fase e números; a mesa escolhida com o total e
 * as fichas de cada um; prize pool, contagem e últimos registros ao lado. Atualiza a cada 5 s
 * (wire:poll, sem WebSocket).
 *
 * Clicar num jogador abre o painel dele (Livewire Jogo\AcoesJogador). "Mudar de lugar" no painel
 * põe a tela no modo de mudança: o próximo lugar clicado recebe o jogador (quebra de mesa).
 *
 * v2: torneio com estrutura de blinds tem relógio na faixa; quem controla o relógio pausa ali e abre
 * o modal com próximo, anterior e ± minutos. v3: o tempo conta e passa de nível no navegador
 * (resources/js/site/relogio-torneio.js, com o pacote de EstadoDoRelogio::paraNavegador()).
 */
class AoVivo extends Component
{
    use EscolheQuemSenta;

    public Jogo $jogo;

    /** jogo_mesa em exibição (?mesa= na URL); vazio = a primeira. */
    #[Url(as: 'mesa', except: null)]
    public ?int $mesa = null;

    /** Jogador com o painel aberto. */
    public ?int $selecionado = null;

    /** Jogador mudando de lugar: o próximo lugar clicado é o novo. */
    public ?int $movendo = null;

    /** Modal da tela: nivel, relogio, link (do relógio), intervalo (abrir), fechar (entradas) ou voltar (fase). */
    public ?string $modal = null;

    /** Motivo de voltar a fase (fica no histórico). */
    public string $motivoCorrecao = '';

    public string $nivel = '';

    public string $blinds = '';

    public ?string $erro = null;

    public ?string $aviso = null;

    /** Jogo finalizado em outro aparelho enquanto a tela estava aberta: vai para a etapa atual. */
    public function hydrate(): void
    {
        if (! EtapaDoJogo::permite($this->jogo, 'ao-vivo')) {
            session()->flash('aviso', "{$this->jogo->nome} não está mais em andamento.");
            $this->redirect(EtapaDoJogo::url($this->jogo) ?? route('jogos.index'));
        }
    }

    public function verMesa(int $jogoMesaId): void
    {
        $this->mesa = $this->jogo->mesas()->findOrFail($jogoMesaId)->id;
        $this->selecionado = null;
    }

    public function clicarAssento(int $jogoMesaId, int $lugar): void
    {
        if ($this->movendo !== null) {
            $this->trocar($this->jogo->mesas()->findOrFail($jogoMesaId), $lugar);

            return;
        }

        $ocupante = $this->jogo->participantes()->where('jogo_mesa_id', $jogoMesaId)->where('lugar', $lugar)->first();

        // Lugar vazio: quem chegou atrasado senta ali (e, no torneio, já entra com o buy-in).
        if ($ocupante === null && $this->quem()->can('mesas.organizar')) {
            $this->selecionado = null;
            $this->abrirLugarVazio($jogoMesaId, $lugar);

            return;
        }

        $this->selecionar($ocupante?->id);
    }

    public function selecionar(?int $participanteId): void
    {
        $this->selecionado = $participanteId === null || $participanteId === $this->selecionado ? null : $participanteId;
    }

    #[On('fechar-jogador')]
    public function fecharJogador(): void
    {
        $this->selecionado = null;
    }

    /** O painel do jogador lançou algo: redesenha a mesa e a faixa na hora, sem esperar o poll. */
    #[On('caixa-mudou')]
    public function caixaMudou(): void {}

    #[On('mudar-lugar')]
    public function mudarLugar(int $participanteId): void
    {
        $this->movendo = $this->jogo->participantes()->findOrFail($participanteId)->id;
        $this->selecionado = null;
        $this->erro = null;
    }

    public function tirarDoLugar(): void
    {
        $this->trocar(null, null);
    }

    public function cancelarMudanca(): void
    {
        $this->movendo = null;
        $this->erro = null;
    }

    private function trocar(?JogoMesa $jogoMesa, ?int $lugar): void
    {
        $participante = $this->jogo->participantes()->findOrFail($this->movendo);

        if ($this->executar(fn () => app(TrocarLugar::class)($participante, $jogoMesa, $lugar, $this->quem()))) {
            $this->movendo = null;
        }
    }

    // ── Nível e blinds (digitados na v1) ──

    public function abrirNivel(): void
    {
        $this->modal = 'nivel';
        $this->nivel = (string) ($this->jogo->nivel_atual ?? '');
        $this->blinds = (string) ($this->jogo->blinds_atual ?? '');
        $this->erro = null;
    }

    public function salvarNivel(): void
    {
        $nivel = trim($this->nivel);

        if ($nivel !== '' && ! ctype_digit($nivel)) {
            $this->erro = 'O nível é um número.';

            return;
        }

        if ($this->executar(fn () => app(AtualizarNivel::class)($this->jogo, $nivel === '' ? null : (int) $nivel, $this->blinds, $this->quem()))) {
            $this->modal = null;
        }
    }

    // ── Relógio (v2, torneio com estrutura de blinds) ──

    public function abrirRelogio(): void
    {
        $this->modal = 'relogio';
        $this->erro = null;
    }

    /** continuar (o INICIAR da primeira vez), pausar, proximo, anterior ou ajustar (± minutos). */
    public function controlarRelogio(string $comando, int $minutos = 0): void
    {
        if ($comando = ComandoRelogio::tryFrom($comando)) {
            $this->executar(fn () => app(ControlarRelogio::class)($this->jogo, $comando, $this->quem(), $minutos));
        }
    }

    // ── Link do relógio (v3): o botão TV abre o link para copiar, abrir ou trocar ──

    public function abrirLink(): void
    {
        $this->modal = 'link';
        $this->erro = null;
    }

    /** Gera outro código: o link antigo para de abrir nas telas que estavam com ele. */
    public function trocarLink(): void
    {
        if ($this->executar(fn () => app(TrocarLinkDoRelogio::class)($this->jogo, $this->quem()))) {
            $this->aviso = 'Link do relógio trocado: mande o novo para as outras telas.';
        }
    }

    // ── Fases do torneio: Abrir intervalo (tela 07) e Fechar entradas (tela 11) ──

    public function abrirFase(): void
    {
        $this->modal = $this->jogo->fase === FaseTorneio::Rebuys ? 'intervalo' : 'fechar';
        $this->erro = null;
    }

    public function abrirIntervalo(): void
    {
        if ($this->executar(fn () => app(AbrirIntervalo::class)($this->jogo, $this->quem()))) {
            $this->modal = null;
            $this->aviso = 'Intervalo aberto: add-on liberado, e quem caiu ainda volta com rebuy até fechar as entradas.';
        }
    }

    public function fecharEntradas(): void
    {
        if ($this->executar(fn () => app(FecharEntradas::class)($this->jogo, $this->quem()))) {
            $this->modal = null;
            $this->aviso = 'Entradas fechadas e prêmios gravados: cada eliminação agora dá a posição.';
        }
    }

    // ── Correção: voltar a fase apertada antes da hora (jogo.corrigir, com motivo, no histórico) ──

    public function abrirVoltarFase(): void
    {
        $this->modal = 'voltar';
        $this->motivoCorrecao = '';
        $this->erro = null;
    }

    public function voltarFase(): void
    {
        $reabriu = $this->jogo->fase === FaseTorneio::SemEntradas;

        if ($this->executar(fn () => app(VoltarFase::class)($this->jogo, $this->motivoCorrecao, $this->quem()))) {
            $this->modal = null;
            $this->aviso = $reabriu
                ? 'Entradas reabertas: buy-in, rebuy e add-on valem de novo. Feche as entradas quando terminar.'
                : 'De volta à fase de rebuys: o add-on fecha até abrir o intervalo de novo.';
        }
    }

    public function fecharModal(): void
    {
        $this->modal = null;
        $this->erro = null;
    }

    /** Roda uma Action; regra ou permissão aparece na tela sem derrubá-la. Deu certo, relê o jogo. */
    private function executar(callable $acao): bool
    {
        $this->erro = null;
        $this->aviso = null;

        try {
            $acao();
        } catch (RegraDoJogo|AuthorizationException $erro) {
            $this->erro = $erro->getMessage();

            return false;
        }

        $this->jogo->refresh();

        return true;
    }

    private function quem(): Usuario
    {
        return auth('web')->user();
    }

    public function render(Calculadora $calculadora, Entradas $entradas, Relogio $relogio): View
    {
        $this->jogo = $relogio->sincronizar($this->jogo); // o nível segue o relógio, a cada atualização

        $mesas = $this->jogo->mesas()->with(['mesa', 'dealer'])->get();
        $mesaAtual = $mesas->firstWhere('id', $this->mesa) ?? $mesas->first();
        $participantes = $this->jogo->participantes()->with('usuario')->get();
        $porJogador = $entradas->porJogador($this->jogo);

        return view('livewire.jogo.ao-vivo', [
            'relogio' => $relogio->estado($this->jogo),
            'resumo' => $calculadora->doJogo($this->jogo),
            'mesas' => $mesas,
            'mesaAtual' => $mesaAtual,
            'ocupantes' => $mesaAtual
                ? $participantes->where('jogo_mesa_id', $mesaAtual->id)->keyBy('lugar')
                : collect(),
            'naMesa' => $participantes->where('status', StatusParticipante::Jogando)->countBy('jogo_mesa_id'),
            'jogando' => $participantes->where('status', StatusParticipante::Jogando)->count(),
            'semLugar' => $participantes->whereNull('jogo_mesa_id')
                ->whereIn('status', [StatusParticipante::Confirmado, StatusParticipante::Jogando])
                ->values(),
            'movendoNome' => $participantes->firstWhere('id', $this->movendo)?->nome(),
            'rodapes' => $this->rodapes($porJogador),
            'contagem' => [
                'buyins' => $porJogador->filter(fn (EntradasDoJogador $e) => $e->buyin !== null)->count(),
                'rebuys' => $porJogador->sum('rebuys'),
                'addons' => $porJogador->sum('addons'),
                'cashOuts' => $participantes->whereNotNull('cash_out')->count(),
            ],
            'ultimos' => $this->jogo->entradas()->validas()->whereNull('vinculada_a')
                ->with(['participante.usuario', 'item'])
                ->latest()->latest('id')->limit(8)->get(),
            'urlResumo' => EtapaDoJogo::url($this->jogo, 'resumo'),
            'premiacao' => $this->jogo->fase === FaseTorneio::SemEntradas
                ? $this->jogo->premiacoes()->get()->map(fn ($premio) => [
                    'posicao' => $premio->posicao,
                    'valor' => $premio->valor,
                    'quem' => $participantes->firstWhere('posicao_final', $premio->posicao)?->nome(),
                ])
                : collect(),
            // Só o que o modal aberto mostra.
            'foraAgora' => in_array($this->modal, ['intervalo', 'fechar'], true) ? $this->foraAgora($participantes) : collect(),
            'semBuyin' => $this->modal === 'fechar'
                ? $participantes->filter(fn (Participante $p) => $p->status === StatusParticipante::Jogando && ! $porJogador->get($p->id)?->buyin)
                    ->map->nome()->values()
                : collect(),
            'quemSenta' => $this->dadosDoLugarVazio(),
            'addon' => $this->modal === 'intervalo'
                ? $this->jogo->itens()->ativos()->where('tipo', TipoItem::Addon)->orderBy('valor')->first()
                : null,
        ]);
    }

    /**
     * Quem está fora numa eliminação provisória e não voltou: ainda volta com rebuy no intervalo e fica
     * eliminado de vez ao fechar as entradas.
     *
     * @param  Collection<int, Participante>  $participantes
     * @return Collection<int, string>
     */
    private function foraAgora(Collection $participantes): Collection
    {
        $ids = $this->jogo->eliminacoes()->validas()->emAberto()->pluck('participante_id');

        return $participantes->whereIn('id', $ids)->map->nome()->values();
    }

    /**
     * Total e fichas de cada assento: buy-in (B, 2, 3…), rebuys (R ou a quantidade) e add-on (A).
     *
     * @param  Collection<int, EntradasDoJogador>  $porJogador
     * @return array<int, array{total: string, fichas: list<array{0: string, 1: ?string}>}>
     */
    private function rodapes(Collection $porJogador): array
    {
        return $porJogador->map(fn (EntradasDoJogador $e) => [
            'total' => Dinheiro::brlCentavos($e->total),
            'fichas' => array_values(array_filter([
                $e->buyin ? [$this->jogo->fichaDoBuyin($e->buyin), null] : null,
                $e->rebuys ? [$e->rebuys > 1 ? (string) $e->rebuys : 'R', 'r'] : null,
                $e->addons ? ['A', 'a'] : null,
            ])),
        ])->all();
    }

    /** Ficha da linha dos últimos registros (opcional sozinho não tem ficha). */
    public function fichaDaEntrada(Entrada $entrada): ?array
    {
        return match ($entrada->tipo) {
            TipoItem::Buyin => [$this->jogo->fichaDoBuyin($entrada->item), null],
            TipoItem::Rebuy => ['R', 'r'],
            TipoItem::Addon => ['A', 'a'],
            TipoItem::Opcional => null,
        };
    }
}
