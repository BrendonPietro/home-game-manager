<?php

namespace App\Livewire\Jogo;

use App\Actions\Jogo\ControlarRelogio;
use App\Actions\Jogo\DispararAviso;
use App\Enums\ComandoRelogio;
use App\Exceptions\RegraDoJogo;
use App\Models\Jogo;
use App\Models\Usuario;
use App\Services\Jogo\Relogio;
use App\Support\EtapaDoJogo;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\View\View;
use Livewire\Component;

/**
 * v3: tela do relógio de quem controla o torneio (/jogos/{jogo}/relogio), boa no celular em pé e
 * deitado: o relógio grande (o mesmo painel do link) e os botões grandes de ANTERIOR, PAUSAR/CONTINUAR,
 * PRÓXIMO e ± minutos, mais o AVISO, que toca o som em todas as telas do link (e aqui, no clique).
 * Quem não tem `relogio.controlar` vê o relógio sem os botões. O tempo anda no navegador (motor do
 * relógio); o poll de 5 s acerta a conta.
 */
class ControleDoRelogio extends Component
{
    public Jogo $jogo;

    public ?string $erro = null;

    public ?string $mensagem = null;

    /** Jogo finalizado em outro aparelho enquanto a tela estava aberta: vai para a etapa atual. */
    public function hydrate(): void
    {
        if (! EtapaDoJogo::permite($this->jogo, 'ao-vivo')) {
            session()->flash('aviso', "{$this->jogo->nome} não está mais em andamento.");
            $this->redirect(EtapaDoJogo::url($this->jogo) ?? route('jogos.index'));
        }
    }

    /** continuar (o INICIAR da primeira vez), pausar, proximo, anterior ou ajustar (± minutos). */
    public function controlar(string $comando, int $minutos = 0): void
    {
        if ($comando = ComandoRelogio::tryFrom($comando)) {
            $this->executar(fn () => app(ControlarRelogio::class)($this->jogo, $comando, $this->quem(), $minutos));
        }
    }

    public function avisar(): void
    {
        if ($this->executar(fn () => app(DispararAviso::class)($this->jogo, $this->quem()))) {
            $this->mensagem = 'Aviso enviado: toca nas telas do link em até 2 s.';
        }
    }

    /** Roda uma Action; regra ou permissão aparece na tela sem derrubá-la. */
    private function executar(callable $acao): bool
    {
        $this->erro = null;
        $this->mensagem = null;

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

    public function render(Relogio $relogio): View
    {
        $this->jogo = $relogio->sincronizar($this->jogo);

        return view('livewire.jogo.controle-do-relogio', [
            'relogio' => $relogio->estado($this->jogo),
            'pode' => $this->quem()->can('relogio.controlar'),
        ]);
    }
}
