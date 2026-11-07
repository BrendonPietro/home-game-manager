<?php

namespace App\Livewire\Jogo;

use App\Actions\Jogo\AdicionarMesa;
use App\Actions\Jogo\AjustarMesa;
use App\Actions\Jogo\IniciarJogo;
use App\Actions\Jogo\RemoverMesa;
use App\Actions\Jogo\SortearLugares;
use App\Actions\Jogo\TrocarLugar;
use App\Enums\StatusParticipante;
use App\Exceptions\RegraDoJogo;
use App\Livewire\Jogo\Concerns\EscolheQuemSenta;
use App\Models\Jogo;
use App\Models\Participante;
use App\Models\Usuario;
use App\Support\EtapaDoJogo;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Tela 06: montar as mesas e iniciar o jogo. Clicar num jogador seleciona; clicar depois num lugar
 * vazio move, num lugar ocupado troca os dois. As regras ficam nas Actions.
 */
class Mesas extends Component
{
    use EscolheQuemSenta;

    public Jogo $jogo;

    public ?int $selecionado = null;

    public ?string $erro = null;

    public function clicarAssento(int $jogoMesaId, int $lugar): void
    {
        $ocupante = $this->jogo->participantes()->where('jogo_mesa_id', $jogoMesaId)->where('lugar', $lugar)->first();

        // Lugar vazio sem ninguém escolhido: abre a escolha de quem senta ali.
        if ($this->selecionado === null && $ocupante === null) {
            $this->abrirLugarVazio($jogoMesaId, $lugar);

            return;
        }

        if ($this->selecionado === null || $ocupante?->id === $this->selecionado) {
            $this->selecionado = $ocupante?->id === $this->selecionado ? null : $ocupante?->id;

            return;
        }

        $mesa = $this->jogo->mesas()->findOrFail($jogoMesaId);
        $participante = $this->jogo->participantes()->findOrFail($this->selecionado);

        if ($this->executar(fn () => app(TrocarLugar::class)($participante, $mesa, $lugar, $this->quem()))) {
            $this->selecionado = null;
        }
    }

    public function selecionar(int $participanteId): void
    {
        $this->selecionado = $this->selecionado === $participanteId ? null : $participanteId;
    }

    /** Leva o jogador selecionado para "sem lugar". */
    public function semLugar(): void
    {
        if ($this->selecionado === null) {
            return;
        }

        $participante = $this->jogo->participantes()->findOrFail($this->selecionado);

        if ($this->executar(fn () => app(TrocarLugar::class)($participante, null, null, $this->quem()))) {
            $this->selecionado = null;
        }
    }

    public function sortear(): void
    {
        $this->selecionado = null;
        $this->executar(fn () => app(SortearLugares::class)($this->jogo, $this->quem()));
    }

    public function ajustarLugares(int $jogoMesaId, int|string $lugares): void
    {
        $mesa = $this->jogo->mesas()->with('dealer')->findOrFail($jogoMesaId);

        $this->executar(fn () => app(AjustarMesa::class)($mesa, (int) $lugares, $mesa->dealer, $this->quem()));
    }

    public function ajustarDealer(int $jogoMesaId, int|string|null $dealerId): void
    {
        $mesa = $this->jogo->mesas()->findOrFail($jogoMesaId);
        $dealer = filled($dealerId) ? Usuario::query()->findOrFail($dealerId) : null;

        $this->executar(fn () => app(AjustarMesa::class)($mesa, $mesa->lugares, $dealer, $this->quem()));
    }

    public function adicionarMesa(): void
    {
        $this->executar(fn () => app(AdicionarMesa::class)($this->jogo, $this->quem()));
    }

    public function removerMesa(int $jogoMesaId): void
    {
        $mesa = $this->jogo->mesas()->findOrFail($jogoMesaId);

        $this->executar(fn () => app(RemoverMesa::class)($mesa, $this->quem()));
    }

    public function iniciar(): void
    {
        if (! $this->executar(fn () => app(IniciarJogo::class)($this->jogo, $this->quem()))) {
            return;
        }

        $jogo = $this->jogo->refresh();

        session()->flash('aviso', "{$jogo->nome} começou.");
        $this->redirect(EtapaDoJogo::url($jogo) ?? route('jogos.index'));
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

        return true;
    }

    private function quem(): Usuario
    {
        return auth('web')->user();
    }

    public function render(): View
    {
        $mesas = $this->jogo->mesas()->with(['dealer', 'participantes.usuario'])->get();
        $semLugar = $this->jogo->participantes()
            ->whereNull('jogo_mesa_id')
            ->whereIn('status', [StatusParticipante::Confirmado, StatusParticipante::Jogando])
            ->with('usuario')
            ->orderBy('id')
            ->get();

        $selecionado = $this->selecionado
            ? Participante::query()->with('usuario')->find($this->selecionado)
            : null;

        return view('livewire.jogo.mesas', [
            'mesas' => $mesas,
            'semLugar' => $semLugar,
            'selecionadoNome' => $selecionado?->usuario->nome_exibicao,
            'selecionadoSentado' => $selecionado?->jogo_mesa_id !== null,
            'dealers' => Usuario::query()->ativos()->dealers()->orderBy('nome')->get(),
            'quemSenta' => $this->dadosDoLugarVazio(),
        ]);
    }
}
