<?php

namespace App\Actions\Jogo;

use App\Actions\Jogo\Concerns\ChecaRegras;
use App\Enums\FaseTorneio;
use App\Enums\StatusJogo;
use App\Enums\StatusParticipante;
use App\Enums\TipoAlteracao;
use App\Exceptions\RegraDoJogo;
use App\Models\Eliminacao;
use App\Models\Entrada;
use App\Models\Jogo;
use App\Models\Pagamento;
use App\Models\Participante;
use App\Models\Usuario;
use App\Services\Jogo\Historico;
use App\Services\Jogo\Posicoes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Cancela a última ação do jogador (entrada, pagamento ou eliminação), com motivo. Nada é apagado.
 *
 * - Entrada leva junto os opcionais comprados com ela; só até Fechar entradas. Rebuy que trouxe o
 *   jogador de volta devolve a eliminação (provisória: as entradas ainda estão abertas).
 * - Eliminação: o jogador volta a jogar; com as entradas fechadas, as posições são refeitas.
 * - Cash-out não entra aqui: registrar de novo corrige.
 */
class DesfazerAcao
{
    use ChecaRegras;

    public function __construct(private Posicoes $posicoes, private Historico $historico) {}

    /** @return string o que foi desfeito, para mostrar na tela */
    public function __invoke(Participante $participante, string $motivo, Usuario $quem): string
    {
        $this->permitir($quem, 'acoes.desfazer', 'Só o Admin do site desfaz ações.');

        $motivo = Str::limit(trim($motivo), 255, '');

        if ($motivo === '') {
            throw new RegraDoJogo('Diga o motivo para desfazer.');
        }

        return DB::transaction(function () use ($participante, $motivo, $quem) {
            $jogo = Jogo::query()->lockForUpdate()->findOrFail($participante->jogo_id);
            $this->exigirStatus($jogo, [StatusJogo::EmAndamento], 'Só dá para desfazer com o jogo em andamento.');

            $participante = $jogo->participantes()->with('usuario')->findOrFail($participante->id);
            $acao = $this->ultimaAcao($participante)
                ?? throw new RegraDoJogo("{$participante->nome()} não tem nada para desfazer.");

            $feito = match (true) {
                $acao instanceof Entrada => $this->entrada($jogo, $participante, $acao, $quem, $motivo),
                $acao instanceof Eliminacao => $this->eliminacao($jogo, $participante, $acao, $quem, $motivo),
                $acao instanceof Pagamento => $this->pagamento($participante, $acao, $quem, $motivo),
            };

            $this->historico->registrar($jogo, $quem, TipoAlteracao::Desfez, rtrim($feito, '.'), $motivo, [
                'tipo' => class_basename($acao),
                'id' => $acao->id,
            ]);

            return $feito;
        });
    }

    /**
     * A mais recente entre entrada (opcional junto de outra não conta sozinho), eliminação e
     * pagamento. Eliminação de quem voltou com rebuy não conta: o rebuy veio depois. No mesmo
     * segundo, vale a ordem natural da noite: entrada, eliminação, pagamento.
     */
    public function ultimaAcao(Participante $participante): Entrada|Eliminacao|Pagamento|null
    {
        $candidatas = [
            $participante->entradas()->validas()->whereNull('vinculada_a')->latest()->latest('id')->first(),
            $participante->eliminacoes()->validas()->whereNull('voltou_com')->latest()->latest('id')->first(),
            $participante->pagamentos()->validas()->latest()->latest('id')->first(),
        ];

        $ultima = null;
        $quando = null;

        foreach ($candidatas as $acao) {
            if ($acao && ($quando === null || $acao->created_at->gte($quando))) {
                [$ultima, $quando] = [$acao, $acao->created_at];
            }
        }

        return $ultima;
    }

    private function entrada(Jogo $jogo, Participante $participante, Entrada $entrada, Usuario $quem, string $motivo): string
    {
        $nome = "{$entrada->item->nome} de {$participante->nome()}";

        if (! $jogo->entradasAbertas()) {
            throw new RegraDoJogo("As entradas já foram fechadas: o {$nome} não se desfaz mais.");
        }

        $entrada->cancelar($quem, $motivo);
        $entrada->opcionais()->validas()->get()->each->cancelar($quem, $motivo);

        $volta = $participante->eliminacoes()->validas()->where('voltou_com', $entrada->id)->first();

        if ($volta) {
            $volta->forceFill(['voltou_com' => null, 'definitiva' => false])->save();
            $participante->forceFill(['status' => StatusParticipante::Eliminado])->save();
        }

        return "{$nome} desfeito.";
    }

    private function eliminacao(Jogo $jogo, Participante $participante, Eliminacao $eliminacao, Usuario $quem, string $motivo): string
    {
        $eliminacao->cancelar($quem, $motivo);
        $participante->forceFill(['status' => StatusParticipante::Jogando])->save();

        if ($jogo->fase === FaseTorneio::SemEntradas) {
            $this->posicoes->recalcular($jogo);
        }

        return "Eliminação de {$participante->nome()} desfeita.";
    }

    private function pagamento(Participante $participante, Pagamento $pagamento, Usuario $quem, string $motivo): string
    {
        $pagamento->cancelar($quem, $motivo);

        return "Pagamento de {$participante->nome()} desfeito.";
    }
}
