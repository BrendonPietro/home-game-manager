<?php

namespace App\Livewire\Jogo\Concerns;

use App\Actions\Jogo\CadastrarJogadorRapido;
use App\Actions\Jogo\ConfirmarJogador;
use App\Actions\Jogo\RegistrarEntrada;
use App\Actions\Jogo\TrocarLugar;
use App\Enums\StatusJogo;
use App\Enums\StatusParticipante;
use App\Enums\TipoItem;
use App\Models\Item;
use App\Models\Participante;
use App\Models\Usuario;
use App\Services\Jogo\TimeChip;
use App\Support\Dinheiro;
use App\Support\Fichas;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Clicar num lugar vazio abre a escolha de quem senta ali (telas 06 e 07): primeiro os confirmados
 * que ainda não têm lugar (quem já sentou some da lista), depois alguém do cadastro que não estava na
 * lista e, por último, um jogador novo. No torneio em andamento, sentar já lança o buy-in padrão, e o
 * componente pode mostrar quanto entregar (`avisarQueSentou`, com o time chip e o bônus de quem pagou antes).
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
        $sentado = null;
        $sentou = $this->executar(function () use ($quem, $mesa, $lugar, &$sentado) {
            DB::transaction(function () use ($quem, $mesa, $lugar, &$sentado) {
                $sentado = $quem();
                app(TrocarLugar::class)($sentado, $mesa, $lugar, $this->quem());
            });
        });

        if (! $sentou) {
            return;
        }

        $this->lugarVazio = null;

        // Sentou com o torneio em andamento: o buy-in acabou de entrar, com as fichas a entregar.
        $buyin = $this->jogo->eTorneio() && $this->jogo->status === StatusJogo::EmAndamento
            ? $sentado->entradas()->validas()->where('tipo', TipoItem::Buyin)->latest('id')->first()
            : null;

        if ($buyin !== null) {
            $this->avisarQueSentou($sentado->nome().' sentou na '.$mesa->nome.' · Lugar '.$lugar
                .($sentado->pagou_antes && $buyin->antecipado ? ', pagou antes' : '')
                .RegistrarEntrada::aEntregar($buyin).'.');
        }
    }

    /** A tela que mostra avisos (a ao vivo) sobrescreve: "Fulano sentou na Mesa 1 · Lugar 3: entregar 60.000 fichas." */
    protected function avisarQueSentou(string $texto): void {}

    /**
     * O que o modal mostra (nulo sem lugar clicado).
     *
     * @return array{titulo: string, confirmados: Collection, cadastro: Collection, buyin: ?string, antecipado: int}|null
     */
    protected function dadosDoLugarVazio(): ?array
    {
        if ($this->lugarVazio === null) {
            return null;
        }

        [$mesaId, $lugar] = $this->lugarVazio;
        $mesa = $this->jogo->mesas()->find($mesaId);

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
            ->sortBy(fn (Participante $p) => mb_strtolower($p->usuario->nome))
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
            'titulo' => $mesa->nome.' · Lugar '.$lugar,
            // Fichas a mais de quem está marcado como "pagou antes" (a lista mostra a marca).
            'antecipado' => $this->jogo->temBonusAntecipado() ? (int) $this->jogo->antecipado_fichas : 0,
            'confirmados' => $confirmados,
            'cadastro' => $cadastro,
            'buyin' => $this->jogo->eTorneio() && $padrao
                ? ($this->jogo->status === StatusJogo::EmAndamento
                    ? 'Sentar já lança o buy-in ('.$padrao->nome.' · '.Dinheiro::brl($padrao->valor).')'.$this->fichasAoSentar($padrao)
                    : 'O buy-in ('.$padrao->nome.' · '.Dinheiro::brl($padrao->valor).') entra quando o torneio começar'.$this->fichasAoSentar($padrao))
                : null,
        ];
    }

    /**
     * ": entregar 30.000 fichas (10.000 do time chip)", como o aviso do lançamento (o time chip só enquanto vale),
     * e "; quem pagou antes, mais 20.000" quando o torneio tem o bônus do pagamento antecipado.
     */
    private function fichasAoSentar(Item $buyin): string
    {
        $timeChip = app(TimeChip::class)->fichasAgora($this->jogo);
        $bonus = $this->jogo->temBonusAntecipado() ? (int) $this->jogo->antecipado_fichas : 0;

        return Fichas::entregar((int) $buyin->fichas + $timeChip, $timeChip)
            .($bonus > 0 ? '; quem pagou antes, mais '.Fichas::numero($bonus) : '');
    }
}
