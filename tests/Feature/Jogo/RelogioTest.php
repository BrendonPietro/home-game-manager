<?php

namespace Tests\Feature\Jogo;

use App\Actions\Jogo\AtualizarNivel;
use App\Actions\Jogo\ControlarRelogio;
use App\Actions\Jogo\IniciarJogo;
use App\Enums\ComandoRelogio;
use App\Enums\TipoItem;
use App\Models\EstruturaBlinds;
use App\Models\Jogo;
use App\Models\Usuario;
use App\Services\Jogo\EstadoDoRelogio;
use App\Services\Jogo\Relogio;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\MontaNoite;
use Tests\TestCase;

/**
 * Relógio da v2 com uma estrutura curta: N1 100/200 e N2 200/400 de 10 min, Intervalo de 5 min,
 * N3 300/600 ante 600 de 10 min.
 */
class RelogioTest extends TestCase
{
    use MontaNoite, RefreshDatabase;

    private EstruturaBlinds $estrutura;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-31 20:00:00'));
        $this->montarNoite();

        $this->estrutura = EstruturaBlinds::query()->create(['nome' => 'Curta']);
        foreach ([[100, 200, 0], [200, 400, 0], 'Intervalo', [300, 600, 600]] as $i => $nivel) {
            $this->estrutura->niveis()->create(is_string($nivel)
                ? ['ordem' => $i + 1, 'e_intervalo' => true, 'nome_intervalo' => $nivel, 'duracao_minutos' => 5]
                : ['ordem' => $i + 1, 'small_blind' => $nivel[0], 'big_blind' => $nivel[1], 'ante' => $nivel[2], 'duracao_minutos' => 10]);
        }

        $this->jogo->update(['estrutura_blinds_id' => $this->estrutura->id]);
        app(Relogio::class)->preparar($this->jogo->fresh());
    }

    private function relogio(ComandoRelogio $comando, int $minutos = 0, ?Usuario $quem = null): Jogo
    {
        return app(ControlarRelogio::class)($this->jogo, $comando, $quem ?? $this->admin, $minutos);
    }

    private function estado(): ?EstadoDoRelogio
    {
        return app(Relogio::class)->estado($this->jogo->fresh());
    }

    /** [título, tempo, correndo, nivel_atual, blinds_atual] depois de sincronizar */
    private function agora(): array
    {
        $jogo = app(Relogio::class)->sincronizar($this->jogo->fresh());
        $estado = app(Relogio::class)->estado($jogo);

        return [$estado->titulo(), $estado->tempo(), $estado->rodando, $jogo->nivel_atual, $jogo->blinds_atual];
    }

    public function test_iniciar_o_jogo_deixa_o_nivel_1_parado(): void
    {
        $jogo = Jogo::factory()->agendado()->comEntradasPadrao()->create(['estrutura_blinds_id' => $this->estrutura->id]);
        $mesa = $jogo->mesas()->create(['mesa_id' => $this->mesa->mesa_id, 'lugares' => 9, 'ordem' => 1]);
        foreach ([1, 2] as $lugar) {
            $jogo->participantes()->create(['usuario_id' => Usuario::factory()->create()->id, 'jogo_mesa_id' => $mesa->id, 'lugar' => $lugar]); // confirmado
        }

        app(IniciarJogo::class)($jogo, $this->admin);

        $jogo->refresh();
        $estado = app(Relogio::class)->estado($jogo);
        $this->assertSame(['Nível 1', '10:00', false, false], [$estado->titulo(), $estado->tempo(), $estado->rodando, $estado->iniciado]);
        $this->assertSame([1, '100 / 200'], [$jogo->nivel_atual, $jogo->blinds_atual]);
        $this->assertSame(1200, $estado->intervaloEm()); // 10 + 10 min até o intervalo
    }

    public function test_corre_pausa_e_avanca_sozinho_pelos_niveis(): void
    {
        $this->relogio(ComandoRelogio::Continuar);
        $this->assertNotNull($this->jogo->fresh()->relogio_iniciado_em);

        $this->travel(4)->minutes();
        $this->assertSame(['Nível 1', '06:00', true, 1, '100 / 200'], $this->agora());

        // Pausado, o tempo não anda.
        $this->relogio(ComandoRelogio::Pausar);
        $this->travel(30)->minutes();
        $this->assertSame(['Nível 1', '06:00', false, 1, '100 / 200'], $this->agora());

        // Passa do nível 1 para o 2 sozinho, sem ninguém na tela.
        $this->relogio(ComandoRelogio::Continuar);
        $this->travel(7)->minutes();
        $this->assertSame(['Nível 2', '09:00', true, 2, '200 / 400'], $this->agora());

        // Intervalo: não conta no número; o nível da noite continua o 2.
        $this->travel(9)->minutes();
        $this->assertSame(['Intervalo', '05:00', true, 2, 'Intervalo'], $this->agora());
        $this->assertTrue($this->estado()->noIntervalo());
        $this->assertNull($this->estado()->intervaloEm());

        // Dois níveis de uma vez (a tela ficou fechada): intervalo acabou, nível 3 com ante.
        $this->travel(7)->minutes();
        $this->assertSame(['Nível 3', '08:00', true, 3, '300 / 600 · ante 600'], $this->agora());

        // No fim do último nível o relógio para em 00:00.
        $this->travel(1)->hours();
        $this->assertSame(['Nível 3', '00:00', false, 3, '300 / 600 · ante 600'], $this->agora());
        $this->assertTrue($this->estado()->acabou());
        $this->assertRegra('A estrutura acabou: não há próximo nível.', fn () => $this->relogio(ComandoRelogio::Continuar));
    }

    public function test_proximo_anterior_e_ajuste_de_minutos(): void
    {
        $this->assertRegra('Já está no primeiro nível.', fn () => $this->relogio(ComandoRelogio::Anterior));
        $this->assertRegra('O relógio já está pausado.', fn () => $this->relogio(ComandoRelogio::Pausar));

        // Parado, o próximo nível começa com o tempo cheio e continua parado.
        $this->relogio(ComandoRelogio::Proximo);
        $this->assertSame(['Nível 2', '10:00', false, 2, '200 / 400'], $this->agora());

        // Correndo, continua correndo.
        $this->relogio(ComandoRelogio::Continuar);
        $this->travel(3)->minutes();
        $this->relogio(ComandoRelogio::Anterior);
        $this->assertSame(['Nível 1', '10:00', true, 1, '100 / 200'], $this->agora());
        $this->assertRegra('O relógio já está correndo.', fn () => $this->relogio(ComandoRelogio::Continuar));

        $this->relogio(ComandoRelogio::Ajustar, 2);
        $this->assertSame('12:00', $this->estado()->tempo());
        $this->relogio(ComandoRelogio::Ajustar, -1);
        $this->assertSame('11:00', $this->estado()->tempo());
        $this->assertRegra('Ajuste de 1 a 60 minutos, para mais ou para menos.', fn () => $this->relogio(ComandoRelogio::Ajustar, 0));
        $this->assertRegra('Ajuste de 1 a 60 minutos, para mais ou para menos.', fn () => $this->relogio(ComandoRelogio::Ajustar, 61));

        // Tirar mais do que falta: o nível acaba agora e o relógio segue para o próximo.
        $this->relogio(ComandoRelogio::Ajustar, -30);
        $this->assertSame(['Nível 2', '10:00', true, 2, '200 / 400'], $this->agora());

        foreach (range(1, 2) as $vez) {
            $this->relogio(ComandoRelogio::Proximo);
        }
        $this->assertRegra('Já está no último nível.', fn () => $this->relogio(ComandoRelogio::Proximo));
    }

    public function test_eliminacao_guarda_o_nivel_do_relogio(): void
    {
        $ana = $this->jogador('Ana');
        foreach ([$ana, $this->jogador('Beto'), $this->jogador('Caio')] as $quem) {
            $this->entrar($quem, TipoItem::Buyin);
        }

        $this->relogio(ComandoRelogio::Continuar);
        $this->travel(12)->minutes(); // nível 2, e ninguém sincronizou

        $this->assertSame(2, $this->eliminar($ana)->nivel);
        $this->assertSame([2, '200 / 400'], [$this->jogo->fresh()->nivel_atual, $this->jogo->fresh()->blinds_atual]);
    }

    public function test_permissao_e_jogo_sem_estrutura(): void
    {
        try {
            $this->relogio(ComandoRelogio::Continuar, quem: $this->operador);
            $this->fail('Operador mexeu no relógio');
        } catch (AuthorizationException $erro) {
            $this->assertSame('Só quem controla o relógio mexe nele.', $erro->getMessage());
        }

        // Com estrutura, o nível não se digita.
        $this->assertRegra(
            'Este torneio segue a estrutura de blinds: mude o nível pelo relógio.',
            fn () => app(AtualizarNivel::class)($this->jogo, 4, '1000 / 2000', $this->admin),
        );

        $this->jogo->update(['estrutura_blinds_id' => null]);
        $this->assertNull($this->estado());
        $this->assertRegra(
            'Este jogo não tem estrutura de blinds: o nível e os blinds são digitados.',
            fn () => $this->relogio(ComandoRelogio::Continuar),
        );
    }

    public function test_jogo_que_comecou_sem_relogio_parte_do_nivel_digitado(): void
    {
        $this->jogo->forceFill(['relogio_nivel_id' => null, 'relogio_segundos' => null, 'nivel_atual' => 2, 'blinds_atual' => '200/400'])->save();

        $this->assertSame(['Nível 2', '10:00', false, 2, '200 / 400'], $this->agora());
        $this->assertSame($this->estrutura->niveis[1]->id, $this->jogo->fresh()->relogio_nivel_id);
    }

    public function test_fracao_do_segundo_para_a_contagem_no_navegador(): void
    {
        $this->relogio(ComandoRelogio::Continuar);
        $this->travelTo(now()->addMilliseconds(3400));

        // Mostra 09:57 (arredondado para cima); o navegador conta a partir de 596,6 s.
        $estado = $this->estado();
        $this->assertSame(['09:57', 596.6], [$estado->tempo(), $estado->restanteExato()]);

        $this->relogio(ComandoRelogio::Pausar);
        $this->assertSame(['09:57', 597.0], [$this->estado()->tempo(), $this->estado()->restanteExato()]);
    }

    public function test_formato_do_tempo(): void
    {
        $this->assertSame(['14:32', '09:05', '00:00', '1:05:00'], array_map(EstadoDoRelogio::formatar(...), [872, 545, -3, 3900]));
    }
}
