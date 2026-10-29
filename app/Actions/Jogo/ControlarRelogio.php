<?php

namespace App\Actions\Jogo;

use App\Actions\Jogo\Concerns\ChecaRegras;
use App\Enums\ComandoRelogio;
use App\Enums\StatusJogo;
use App\Exceptions\RegraDoJogo;
use App\Models\EstruturaNivel;
use App\Models\Jogo;
use App\Models\Usuario;
use App\Services\Jogo\EstadoDoRelogio;
use App\Services\Jogo\Relogio;
use Illuminate\Support\Facades\DB;

/**
 * v2: o relógio do torneio com estrutura de blinds. Iniciar/continuar, pausar, próximo e anterior
 * (o nível começa com o tempo cheio e o relógio continua correndo ou parado) e ± minutos no nível
 * atual (fica entre 0 e 4 h). Quem controla: permissão `relogio.controlar` (Admin do site).
 */
class ControlarRelogio
{
    use ChecaRegras;

    private const MAXIMO = 4 * 3600;

    public function __construct(private Relogio $relogio) {}

    public function __invoke(Jogo $jogo, ComandoRelogio $comando, Usuario $quem, int $minutos = 0): Jogo
    {
        $this->permitir($quem, 'relogio.controlar', 'Só quem controla o relógio mexe nele.');

        return DB::transaction(function () use ($jogo, $comando, $minutos): Jogo {
            $jogo = Jogo::query()->lockForUpdate()->findOrFail($jogo->id);
            $this->exigirStatus($jogo, [StatusJogo::EmAndamento], 'O relógio só corre com o jogo em andamento.');

            $estado = $this->relogio->estado($jogo)
                ?? throw new RegraDoJogo('Este jogo não tem estrutura de blinds: o nível e os blinds são digitados.');

            [$nivel, $segundos, $rodando] = $this->destino($estado, $comando, $minutos);
            $this->relogio->posicionar($jogo, $nivel, $segundos, $rodando, $estado->agora);

            return $jogo;
        });
    }

    /** @return array{EstruturaNivel, int, bool} nível, segundos que faltam, correndo */
    private function destino(EstadoDoRelogio $estado, ComandoRelogio $comando, int $minutos): array
    {
        switch ($comando) {
            case ComandoRelogio::Continuar:
                if ($estado->rodando) {
                    throw new RegraDoJogo('O relógio já está correndo.');
                }
                if ($estado->acabou()) {
                    throw new RegraDoJogo('A estrutura acabou: não há próximo nível.');
                }

                return [$estado->nivel(), $estado->restante, true];

            case ComandoRelogio::Pausar:
                if (! $estado->rodando) {
                    throw new RegraDoJogo('O relógio já está pausado.');
                }

                return [$estado->nivel(), $estado->restante, false];

            case ComandoRelogio::Proximo:
                $nivel = $estado->proximo() ?? throw new RegraDoJogo('Já está no último nível.');

                return [$nivel, $nivel->duracao_minutos * 60, $estado->rodando];

            case ComandoRelogio::Anterior:
                $nivel = $estado->anterior() ?? throw new RegraDoJogo('Já está no primeiro nível.');

                return [$nivel, $nivel->duracao_minutos * 60, $estado->rodando];

            case ComandoRelogio::Ajustar:
                if ($minutos === 0 || abs($minutos) > 60) {
                    throw new RegraDoJogo('Ajuste de 1 a 60 minutos, para mais ou para menos.');
                }

                return [$estado->nivel(), min(self::MAXIMO, max(0, $estado->restante + $minutos * 60)), $estado->rodando];
        }
    }
}
