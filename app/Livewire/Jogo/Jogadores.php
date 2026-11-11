<?php

namespace App\Livewire\Jogo;

use App\Actions\Jogo\CadastrarJogadorRapido;
use App\Actions\Jogo\ConfirmarJogador;
use App\Actions\Jogo\DesconfirmarJogador;
use App\Actions\Jogo\MarcarPagamentoAntecipado;
use App\Exceptions\RegraDoJogo;
use App\Models\Jogo;
use App\Models\Participante;
use App\Models\Usuario;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Tela 05: quem vai jogar hoje. Clicar no cartão confirma ou tira da lista; NOVO JOGADOR faz o
 * cadastro rápido e já confirma. No torneio com bônus do pagamento antecipado, quem tem a permissão marca
 * na lista quem pagou antes (ganha as fichas ao sentar). As regras ficam nas Actions; aqui só se mostra o resultado.
 */
class Jogadores extends Component
{
    public Jogo $jogo;

    #[Url(except: '')]
    public string $busca = '';

    #[Url(except: 'todos')]
    public string $filtro = 'todos';

    public bool $novo = false;

    public string $novoNome = '';

    public string $novoApelido = '';

    public ?string $erro = null;

    public function alternar(int $usuarioId): void
    {
        $jogador = Usuario::query()->findOrFail($usuarioId);
        $confirmado = $this->confirmadosIds()->contains($usuarioId);

        $this->executar(fn () => $confirmado
            ? app(DesconfirmarJogador::class)($this->jogo, $jogador, $this->quem())
            : app(ConfirmarJogador::class)($this->jogo, $jogador, $this->quem()));
    }

    public function remover(int $usuarioId): void
    {
        $jogador = Usuario::query()->findOrFail($usuarioId);

        $this->executar(fn () => app(DesconfirmarJogador::class)($this->jogo, $jogador, $this->quem()));
    }

    /** Pagou antes (ou tira a marca): ganha as fichas do bônus junto do buy-in. */
    public function alternarAntecipado(int $participanteId): void
    {
        $participante = $this->jogo->participantes()->findOrFail($participanteId);

        $this->executar(fn () => app(MarcarPagamentoAntecipado::class)($participante, ! $participante->pagou_antes, $this->quem()));
    }

    public function abrirNovo(): void
    {
        $this->reset('novoNome', 'novoApelido', 'erro');
        $this->resetValidation();
        $this->novo = true;
    }

    public function fecharNovo(): void
    {
        $this->novo = false;
    }

    public function cadastrar(): void
    {
        $this->validate(
            ['novoNome' => ['required', 'string', 'max:255'], 'novoApelido' => ['nullable', 'string', 'max:60']],
            attributes: ['novoNome' => 'nome', 'novoApelido' => 'apelido'],
        );

        $ok = $this->executar(fn () => app(CadastrarJogadorRapido::class)($this->jogo, $this->novoNome, $this->novoApelido, $this->quem()));

        if ($ok) {
            $this->novo = false;
            $this->reset('novoNome', 'novoApelido');
        }
    }

    /** Roda uma Action e mostra a mensagem de regra ou de permissão, sem derrubar a tela. */
    private function executar(callable $acao): bool
    {
        $this->erro = null;

        try {
            $acao();
        } catch (RegraDoJogo|AuthorizationException $erro) {
            $this->erro = $erro->getMessage();

            return false;
        }

        unset($this->confirmados, $this->usuarios);

        return true;
    }

    private function quem(): Usuario
    {
        return auth('web')->user();
    }

    /** @return Collection<int, int> */
    private function confirmadosIds(): Collection
    {
        return $this->confirmados->pluck('usuario_id');
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, Participante> na ordem em que foram confirmados */
    #[Computed]
    public function confirmados()
    {
        return $this->jogo->participantes()->with('usuario')->orderBy('id')->get();
    }

    /** @return Collection<int, Usuario> jogadores ativos, filtrados pela busca e pelo filtro */
    #[Computed]
    public function usuarios(): Collection
    {
        $busca = Str::lower(Str::ascii(trim($this->busca)));
        $confirmados = $this->confirmadosIds();

        $usuarios = match ($this->filtro) {
            'frequentes' => Usuario::query()->ativos()
                ->whereHas('participacoes')
                ->withCount('participacoes')
                ->orderByDesc('participacoes_count')
                ->limit(12)
                ->get(),
            default => Usuario::query()->ativos()->get(),
        };

        return $usuarios
            ->when($this->filtro === 'selecionados', fn (Collection $c) => $c->filter(fn (Usuario $u) => $confirmados->contains($u->id)))
            ->when($busca !== '', fn (Collection $c) => $c->filter(fn (Usuario $u) => Str::contains(
                Str::lower(Str::ascii($u->nome.' '.$u->apelido)),
                $busca,
            )))
            ->when($this->filtro !== 'frequentes', fn (Collection $c) => $c->sortBy(fn (Usuario $u) => Str::lower(Str::ascii($u->nome_exibicao))))
            ->values();
    }

    #[Computed]
    public function totalAtivos(): int
    {
        return Usuario::query()->ativos()->count();
    }

    public function render(): View
    {
        $this->jogo->loadMissing('mesas');

        return view('livewire.jogo.jogadores', [
            'lugares' => $this->jogo->mesas->sum('lugares'),
            'quantidadeDeMesas' => $this->jogo->mesas->count(),
        ]);
    }
}
