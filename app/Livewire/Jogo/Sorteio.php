<?php

namespace App\Livewire\Jogo;

use App\Actions\Jogo\CadastrarJogadorRapido;
use App\Actions\Jogo\ConfirmarJogador;
use App\Actions\Jogo\RegistrarEntrada;
use App\Actions\Jogo\SortearLugares;
use App\Enums\StatusJogo;
use App\Enums\StatusParticipante;
use App\Enums\TipoItem;
use App\Exceptions\RegraDoJogo;
use App\Models\Jogo;
use App\Models\JogoMesa;
use App\Models\Participante;
use App\Models\Usuario;
use App\Support\Dinheiro;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Modal Jogadores (telas 06 e 07, botão JOGADORES): marcar quem chegou, pôr na lista alguém do cadastro
 * ou um jogador novo e sortear o lugar deles nas mesas escolhidas (SortearLugares). Para sentar num lugar
 * certo, clica-se no lugar vazio (Quem senta aqui).
 *
 * Abre pelo evento `abrir-sorteio`. Depois de sortear, mostra onde cada um senta (e, no torneio, quantas
 * fichas entregar) e avisa a tela de baixo (`caixa-mudou`).
 */
class Sorteio extends Component
{
    public Jogo $jogo;

    public bool $aberto = false;

    /** Lista em exibição: sem-lugar ou sentados (para sortear de novo). */
    public string $aba = 'sem-lugar';

    /** @var list<int> participantes marcados */
    public array $escolhidos = [];

    /** @var list<int> mesas do sorteio */
    public array $mesas = [];

    public string $busca = '';

    public string $novoNome = '';

    public string $novoApelido = '';

    /** @var list<array{nome: string, onde: string, entregar: ?string}>|null depois de sortear */
    public ?array $resultado = null;

    public ?string $erro = null;

    public ?string $aviso = null;

    #[On('abrir-sorteio')]
    public function abrir(): void
    {
        $this->aberto = true;
        $this->aba = 'sem-lugar';
        $this->escolhidos = [];
        $this->mesas = $this->jogo->mesas()->pluck('id')->all();
        $this->busca = $this->novoNome = $this->novoApelido = '';
        $this->resultado = $this->erro = $this->aviso = null;
    }

    public function fechar(): void
    {
        $this->aberto = false;
        $this->resultado = null;
    }

    public function verAba(string $aba): void
    {
        $this->aba = $aba === 'sentados' ? 'sentados' : 'sem-lugar';
    }

    public function alternar(int $participanteId): void
    {
        $this->escolhidos = in_array($participanteId, $this->escolhidos, true)
            ? array_values(array_diff($this->escolhidos, [$participanteId]))
            : [...$this->escolhidos, $participanteId];
    }

    /** Marca todos da lista em exibição (com a busca). */
    public function marcarTodos(): void
    {
        $this->escolhidos = array_values(array_unique([...$this->escolhidos, ...$this->lista()->pluck('id')->all()]));
    }

    public function limpar(): void
    {
        $this->escolhidos = [];
    }

    public function alternarMesa(int $jogoMesaId): void
    {
        $this->mesas = in_array($jogoMesaId, $this->mesas, true)
            ? array_values(array_diff($this->mesas, [$jogoMesaId]))
            : [...$this->mesas, $jogoMesaId];
    }

    /** Alguém do cadastro que não estava na lista: confirma e já fica marcado. */
    public function adicionarDoCadastro(int $usuarioId): void
    {
        $usuario = Usuario::query()->ativos()->findOrFail($usuarioId);

        $this->executar(function () use ($usuario) {
            $participante = app(ConfirmarJogador::class)($this->jogo, $usuario, $this->quem());
            $this->marcar($participante, "{$participante->nome()} entrou na lista");
        });
    }

    /** Jogador novo (cadastro rápido): cadastra, confirma e já fica marcado. */
    public function cadastrar(): void
    {
        $this->executar(function () {
            $participante = app(CadastrarJogadorRapido::class)($this->jogo, $this->novoNome, $this->novoApelido, $this->quem());
            $this->novoNome = $this->novoApelido = '';
            $this->marcar($participante, "{$participante->nome()} cadastrado e na lista");
        });
    }

    public function sortear(): void
    {
        $this->executar(function () {
            $sorteados = app(SortearLugares::class)($this->jogo, $this->quem(), $this->escolhidos, $this->mesas);

            $this->resultado = array_map(fn (array $s): array => [
                'nome' => $s['participante']->usuario->nome_com_apelido,
                'onde' => $s['mesa']->nome.' · Lugar '.$s['lugar'],
                'entregar' => $s['buyin']
                    ? ltrim(RegistrarEntrada::aEntregar($s['buyin']), ': ').($s['participante']->pagou_antes && $s['buyin']->antecipado ? ' · pagou antes' : '')
                    : null,
            ], $sorteados);
            $this->escolhidos = [];
            $this->dispatch('caixa-mudou');
        });
    }

    /** Volta para a lista depois de ver o resultado. */
    public function sortearMais(): void
    {
        $this->resultado = null;
    }

    private function marcar(Participante $participante, string $aviso): void
    {
        $this->escolhidos = array_values(array_unique([...$this->escolhidos, $participante->id]));
        $this->busca = '';
        $this->aba = 'sem-lugar';
        $this->aviso = $aviso;
        $this->dispatch('caixa-mudou');
    }

    private function executar(callable $acao): void
    {
        $this->erro = $this->aviso = null;

        try {
            $acao();
        } catch (RegraDoJogo|AuthorizationException $erro) {
            $this->erro = $erro->getMessage();
        }
    }

    private function quem(): Usuario
    {
        return auth('web')->user();
    }

    /** Quem aparece na aba: sem lugar ou sentados, ainda no jogo, com a busca. */
    private function lista(): Collection
    {
        $busca = mb_strtolower(trim($this->busca));

        return $this->jogo->participantes()->with(['usuario', 'jogoMesa', 'opcionaisPagos'])
            ->whereIn('status', [StatusParticipante::Confirmado, StatusParticipante::Jogando])
            ->when($this->aba === 'sem-lugar', fn ($q) => $q->whereNull('jogo_mesa_id'), fn ($q) => $q->whereNotNull('jogo_mesa_id'))
            ->get()
            ->filter(fn (Participante $p) => $busca === ''
                || str_contains(mb_strtolower($p->usuario->nome), $busca)
                || str_contains(mb_strtolower((string) $p->usuario->apelido), $busca))
            ->sortBy(fn (Participante $p) => $this->aba === 'sentados'
                ? sprintf('%04d-%02d', $p->jogoMesa?->ordem ?? 0, $p->lugar)
                : mb_strtolower($p->usuario->nome))
            ->values();
    }

    public function render(): View
    {
        if (! $this->aberto) {
            return view('livewire.jogo.sorteio', ['aberto' => false]);
        }

        $participantes = $this->jogo->participantes()->get();
        $noJogo = $participantes->reject(fn (Participante $p) => $p->estaFora());
        $escolhidos = $participantes->whereIn('id', $this->escolhidos);
        $mesas = $this->jogo->mesas()->get();

        // Lugares livres de cada mesa para quem vai sentar: os vagos, o de quem saiu e o de quem foi marcado.
        $ficam = $noJogo->whereNotNull('jogo_mesa_id')->whereNotIn('id', $this->escolhidos);
        $livres = $mesas->mapWithKeys(fn (JogoMesa $m) => [$m->id => $m->lugares - $ficam->where('jogo_mesa_id', $m->id)->count()]);

        $busca = trim($this->busca);
        $buyins = $this->jogo->itens()->ativos()->where('tipo', TipoItem::Buyin)->get();
        $buyin = $buyins->firstWhere('padrao', true) ?? $buyins->first();

        return view('livewire.jogo.sorteio', [
            'aberto' => true,
            'lista' => $this->lista(),
            'semLugar' => $noJogo->whereNull('jogo_mesa_id')->count(),
            'sentados' => $noJogo->whereNotNull('jogo_mesa_id')->count(),
            'quantos' => $escolhidos->count(),
            'mesasDoJogo' => $mesas,
            'livres' => $livres,
            'livresMarcadas' => $livres->only($this->mesas)->sum(),
            'cadastro' => mb_strlen($busca) < 2 ? collect() : Usuario::query()->ativos()
                ->whereDoesntHave('participacoes', fn ($q) => $q->where('jogo_id', $this->jogo->id))
                ->where(fn ($q) => $q->where('nome', 'like', '%'.addcslashes($busca, '%_\\').'%')
                    ->orWhere('apelido', 'like', '%'.addcslashes($busca, '%_\\').'%'))
                ->orderBy('nome')
                ->limit(8)
                ->get(),
            'entraComBuyin' => $this->jogo->eTorneio() && $this->jogo->status === StatusJogo::EmAndamento && $buyin
                ? 'Quem chegou já entra com o buy-in ('.$buyin->nome.' · '.Dinheiro::brl($buyin->valor).')'
                : null,
            'bonus' => $this->jogo->temBonusAntecipado() ? (int) $this->jogo->antecipado_fichas : 0,
        ]);
    }
}
