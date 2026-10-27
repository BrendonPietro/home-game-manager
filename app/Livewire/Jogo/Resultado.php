<?php

namespace App\Livewire\Jogo;

use App\Actions\Jogo\DefinirResultado;
use App\Enums\StatusParticipante;
use App\Enums\TipoItem;
use App\Exceptions\RegraDoJogo;
use App\Models\Jogo;
use App\Models\Participante;
use App\Models\Usuario;
use App\Services\Jogo\SaldoDoJogador;
use App\Services\Jogo\Saldos;
use App\Support\EtapaDoJogo;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Tela 13: resultado e posições. Torneio: quem caiu já tem a posição; quem ainda está em jogo
 * recebe a posição aqui (DefinirResultado). Cash game: o resultado de cada um é cash-out − investido,
 * do maior lucro para o menor; quem ainda não fez cash-out aparece pendente e o painel do jogador
 * (clicar na linha) registra o cash-out.
 */
class Resultado extends Component
{
    public Jogo $jogo;

    /** @var array<int|string, string> participanteId => posição escolhida para quem está em jogo */
    public array $posicoes = [];

    public ?int $selecionado = null;

    public ?string $erro = null;

    public ?string $aviso = null;

    /** Jogo que deixou o resultado (ex.: voltou ao ao vivo por desfazer) vai para a etapa atual. */
    public function hydrate(): void
    {
        if (! EtapaDoJogo::permite($this->jogo, 'resultado')) {
            session()->flash('aviso', 'O resultado não abre agora.');
            $this->redirect(EtapaDoJogo::url($this->jogo) ?? route('jogos.index'));
        }
    }

    /**
     * Uma posição só pode ser de um: escolher a de outro tira dele. Com o array ainda vazio o
     * navegador manda o array inteiro (sem chave): aí não há o que tirar.
     */
    public function updatedPosicoes(mixed $posicao, ?string $participanteId = null): void
    {
        if ($participanteId === null || is_array($posicao)) {
            return;
        }

        $posicao = (string) $posicao;

        foreach ($this->posicoes as $outro => $escolhida) {
            if ((string) $outro !== $participanteId && $escolhida === $posicao && $posicao !== '') {
                $this->posicoes[$outro] = '';
            }
        }
    }

    public function salvar(): void
    {
        $escolhidas = array_filter($this->posicoes, fn ($posicao) => $posicao !== '' && $posicao !== null);
        $this->erro = null;
        $this->aviso = null;

        try {
            app(DefinirResultado::class)($this->jogo, $escolhidas, $this->quem());
        } catch (RegraDoJogo|AuthorizationException $erro) {
            $this->erro = $erro->getMessage();

            return;
        }

        $this->posicoes = [];
        $this->aviso = 'Resultado gravado.';
    }

    public function selecionar(int $participanteId): void
    {
        $this->selecionado = $this->jogo->participantes()->findOrFail($participanteId)->id;
    }

    #[On('fechar-jogador')]
    public function fecharJogador(): void
    {
        $this->selecionado = null;
    }

    #[On('caixa-mudou')]
    public function caixaMudou(): void {}

    private function quem(): Usuario
    {
        return auth('web')->user();
    }

    public function render(Saldos $saldos): View
    {
        $jogaram = $this->jogo->participantes()->with(['usuario', 'jogoMesa.mesa'])
            ->whereHas('entradas', fn ($entradas) => $entradas->validas()->where('tipo', TipoItem::Buyin))
            ->get();
        $contas = $saldos->doJogo($this->jogo);
        $torneio = $this->jogo->eTorneio();

        $emJogo = $torneio && ! $this->jogo->estaFinalizado()
            ? $jogaram->filter(fn (Participante $p) => $p->status === StatusParticipante::Jogando && $p->posicao_final === null)->values()
            : collect();

        return view('livewire.jogo.resultado', [
            'linhas' => $torneio ? $this->doTorneio($jogaram) : $this->doCashGame($jogaram, $contas),
            'contas' => $contas,
            'emJogo' => $emJogo->count() > 1 ? $emJogo : collect(),
            'podio' => $torneio ? $this->podio($jogaram) : collect(),
            'urlFechamento' => EtapaDoJogo::url($this->jogo, 'fechamento'),
        ]);
    }

    /**
     * Quem ainda está em jogo primeiro (vai ficar do 1º ao Nº), pelo nome; depois do 1º ao último.
     *
     * @param  Collection<int, Participante>  $jogaram
     * @return Collection<int, array{participante: Participante, posicao: ?int}>
     */
    private function doTorneio(Collection $jogaram): Collection
    {
        return $jogaram
            ->sortBy(fn (Participante $p) => [$p->posicao_final ?? 0, $p->nome()])
            ->map(fn (Participante $p) => ['participante' => $p, 'posicao' => $p->posicao_final])
            ->values();
    }

    /**
     * Do maior lucro para o menor; quem não fez cash-out fica no fim, sem posição.
     *
     * @param  Collection<int, Participante>  $jogaram
     * @param  Collection<int, SaldoDoJogador>  $contas
     * @return Collection<int, array{participante: Participante, posicao: ?int}>
     */
    private function doCashGame(Collection $jogaram, Collection $contas): Collection
    {
        [$sairam, $naMesa] = $jogaram->partition(fn (Participante $p) => $p->cash_out !== null);

        return $sairam->sortByDesc(fn (Participante $p) => $contas[$p->id]->resultado())->values()
            ->map(fn (Participante $p, int $i) => ['participante' => $p, 'posicao' => $i + 1])
            ->concat($naMesa->sortBy(fn (Participante $p) => $p->nome())->map(fn (Participante $p) => ['participante' => $p, 'posicao' => null]))
            ->values();
    }

    /**
     * As posições premiadas, com quem está nelas.
     *
     * @param  Collection<int, Participante>  $jogaram
     * @return Collection<int, array{posicao: int, percentual: string, valor: string, quem: ?Participante}>
     */
    private function podio(Collection $jogaram): Collection
    {
        return $this->jogo->premiacoes()->get()->map(fn ($premio) => [
            'posicao' => $premio->posicao,
            'percentual' => $premio->percentual,
            'valor' => $premio->valor,
            'quem' => $jogaram->firstWhere('posicao_final', $premio->posicao),
        ]);
    }
}
