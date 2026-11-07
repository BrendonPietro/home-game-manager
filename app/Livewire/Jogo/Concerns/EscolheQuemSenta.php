<?php

namespace App\Livewire\Jogo\Concerns;

use App\Actions\Jogo\CadastrarJogadorRapido;
use App\Actions\Jogo\ConfirmarJogador;
use App\Actions\Jogo\TrocarLugar;
use App\Enums\StatusJogo;
use App\Enums\StatusParticipante;
use App\Enums\TipoItem;
use App\Models\Participante;
use App\Models\Usuario;
use App\Support\Dinheiro;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Clicar num lugar vazio abre a escolha de quem senta ali (telas 06 e 07): primeiro os confirmados
 * que ainda não têm lugar (quem já sentou some da lista), depois alguém do cadastro que não estava na
 * lista e, por último, um jogador novo. No torneio em andamento, sentar já lança o buy-in padrão.
 *
 * Usa do componente: `$jogo`, `$erro`, `executar(callable): bool` e `quem()`.
 */
trait EscolheQuemSenta
{
    /** Lugar vazio clicado: [jogoMesaId, lugar]. */
    public ?array $lugarVazio = null;

    public string $busca = '';

    public string $novoNome = '';

    public string $novoApelido = '';

    protected function abrirLugarVazio(int $jogoMesaId, int $lugar): void
    {
        $this->lugarVazio = [$this->jogo->mesas()->findOrFail($jogoMesaId)->id, $lugar];
        $this->busca = $this->novoNome = $this->novoApelido = '';
        $this->erro = null;
    }

    public function fecharLugarVazio(): void
    {
        $this->lugarVazio = null;
        $this->erro = null;
    }

    /** Um confirmado (da lista) senta no lugar. */
    public function sentarNoLugar(int $participanteId): void
    {
        $participante = $this->jogo->participantes()->findOrFail($participanteId);

        $this->sentar(fn () => $participante);
    }

    /** Alguém do cadastro que não estava na lista: confirma e senta. */
    public function sentarDoCadastro(int $usuarioId): void
    {
        $usuario = Usuario::query()->ativos()->findOrFail($usuarioId);

        $this->sentar(fn () => app(ConfirmarJogador::class)($this->jogo, $usuario, $this->quem()));
    }

    /** Jogador novo (cadastro rápido): cadastra, confirma e senta. */
    public function cadastrarNoLugar(): void
    {
        $this->sentar(fn () => app(CadastrarJogadorRapido::class)($this->jogo, $this->novoNome, $this->novoApelido, $this->quem()));
    }

    /** @param  callable(): Participante  $quem  quem vai sentar (confirmar e cadastrar acontecem junto) */
    private function sentar(callable $quem): void
    {
        if ($this->lugarVazio === null) {
            return;
        }

        [$mesaId, $lugar] = $this->lugarVazio;
        $mesa = $this->jogo->mesas()->findOrFail($mesaId);

        // Tudo ou nada: se não der para sentar (ex.: entradas fechadas), o cadastro também não fica.
        $sentou = $this->executar(fn () => DB::transaction(
            fn () => app(TrocarLugar::class)($quem(), $mesa, $lugar, $this->quem()),
        ));

        if ($sentou) {
            $this->lugarVazio = null;
        }
    }

    /**
     * O que o modal mostra (nulo sem lugar clicado).
     *
     * @return array{titulo: string, confirmados: Collection, cadastro: Collection, buyin: ?string}|null
     */
    protected function dadosDoLugarVazio(): ?array
    {
        if ($this->lugarVazio === null) {
            return null;
        }

        [$mesaId, $lugar] = $this->lugarVazio;
        $mesa = $this->jogo->mesas()->with('mesa')->find($mesaId);

        if ($mesa === null) {
            return null;
        }

        $busca = mb_strtolower(trim($this->busca));
        $bate = fn (?string $texto): bool => $busca === '' || str_contains(mb_strtolower((string) $texto), $busca);

        $confirmados = $this->jogo->participantes()->with('usuario')
            ->whereNull('jogo_mesa_id')
            ->where('status', StatusParticipante::Confirmado)
            ->get()
            ->filter(fn (Participante $p) => $bate($p->usuario->nome) || $bate($p->usuario->apelido))
            ->sortBy(fn (Participante $p) => mb_strtolower($p->nome()))
            ->values();

        $cadastro = mb_strlen($busca) < 2 ? collect() : Usuario::query()->ativos()
            ->whereDoesntHave('participacoes', fn ($q) => $q->where('jogo_id', $this->jogo->id))
            ->where(fn ($q) => $q->where('nome', 'like', '%'.addcslashes($busca, '%_\\').'%')
                ->orWhere('apelido', 'like', '%'.addcslashes($busca, '%_\\').'%'))
            ->orderBy('nome')
            ->limit(8)
            ->get();

        $buyins = $this->jogo->itens()->ativos()->where('tipo', TipoItem::Buyin)->get();
        $padrao = $buyins->firstWhere('padrao', true) ?? $buyins->first(); // o mesmo do RegistrarEntrada::buyinPadrao

        return [
            'titulo' => $mesa->mesa->nome.' · Lugar '.$lugar,
            'confirmados' => $confirmados,
            'cadastro' => $cadastro,
            'buyin' => $this->jogo->eTorneio() && $padrao
                ? ($this->jogo->status === StatusJogo::EmAndamento
                    ? 'Sentar já lança o buy-in ('.$padrao->nome.' · '.Dinheiro::brl($padrao->valor).')'
                    : 'O buy-in ('.$padrao->nome.' · '.Dinheiro::brl($padrao->valor).') entra quando o torneio começar')
                : null,
        ];
    }
}
