<?php

namespace Tests\Feature\Jogo;

use App\Actions\Jogo\AbrirIntervalo;
use App\Actions\Jogo\DesfazerAcao;
use App\Actions\Jogo\FecharEntradas;
use App\Enums\StatusParticipante;
use App\Enums\TipoItem;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MontaNoite;
use Tests\TestCase;

class DesfazerAcaoTest extends TestCase
{
    use MontaNoite;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->montarNoite();
    }

    public function test_desfaz_a_ultima_acao_com_motivo_sem_apagar(): void
    {
        $joao = $this->jogador('João');
        $this->entrar($joao, TipoItem::Buyin);
        $pagamento = $this->pagar($joao, 10000); // no mesmo segundo do buy-in: o pagamento é o último

        $this->assertSame('Pagamento de João desfeito.', $this->desfazer($joao, 'Pix não caiu'));

        $pagamento->refresh();
        $this->assertNotNull($pagamento->cancelado_em);
        $this->assertSame([$this->admin->id, 'Pix não caiu'], [$pagamento->cancelado_por, $pagamento->motivo_cancelamento]);

        $this->assertSame('Buy-in de João desfeito.', $this->desfazer($joao));
        $this->assertRegra('João não tem nada para desfazer.', fn () => $this->desfazer($joao));
        $this->assertSame([1, 1], [$this->jogo->entradas()->count(), $this->jogo->pagamentos()->count()]);
    }

    public function test_entrada_leva_os_opcionais_junto(): void
    {
        $this->jogo->itens()->create([
            'tipo' => TipoItem::Opcional, 'nome' => 'Caixinha', 'valor' => 5, 'compravel_com' => ['buyin'],
            'percentual_premiacao' => 0, 'obrigatorio' => true,
        ]);
        $joao = $this->jogador('João');
        $this->entrar($joao, TipoItem::Buyin);

        $this->desfazer($joao);

        $this->assertSame([0, 2], [$this->jogo->entradas()->validas()->count(), $this->jogo->entradas()->canceladas()->count()]);
    }

    public function test_rebuy_desfeito_devolve_a_eliminacao(): void
    {
        $joao = $this->jogador('João');
        $this->entrar($joao, TipoItem::Buyin);
        $this->entrar($this->jogador('Lucas'), TipoItem::Buyin);
        $eliminacao = $this->eliminar($joao);
        $this->entrar($joao, TipoItem::Rebuy);

        $this->assertSame('Rebuy de João desfeito.', $this->desfazer($joao));
        $this->assertSame(StatusParticipante::Eliminado, $joao->fresh()->status);
        $this->assertSame([null, false], [$eliminacao->fresh()->voltou_com, $eliminacao->fresh()->definitiva]);

        // Volta de novo e o rebuy é desfeito já no intervalo: continua provisória (ainda pode voltar).
        $this->entrar($joao, TipoItem::Rebuy);
        app(AbrirIntervalo::class)($this->jogo, $this->admin);
        $this->desfazer($joao);

        $this->assertFalse($eliminacao->fresh()->definitiva);
        $this->assertSame(StatusParticipante::Eliminado, $joao->fresh()->status);
    }

    public function test_eliminacao_desfeita_refaz_as_posicoes(): void
    {
        [$ana, $beto] = array_map(function (string $nome) {
            $jogador = $this->jogador($nome);
            $this->entrar($jogador, TipoItem::Buyin);

            return $jogador;
        }, ['Ana', 'Beto', 'Caio', 'Dudu']);

        app(AbrirIntervalo::class)($this->jogo, $this->admin);
        app(FecharEntradas::class)($this->jogo, $this->admin);
        $this->eliminar($ana);
        $this->eliminar($beto);
        $this->assertSame([4, 3], [$ana->fresh()->posicao_final, $beto->fresh()->posicao_final]);

        $this->assertSame('Eliminação de Ana desfeita.', $this->desfazer($ana));

        $this->assertSame(StatusParticipante::Jogando, $ana->fresh()->status);
        $this->assertNull($ana->fresh()->posicao_final);
        $this->assertSame(4, $beto->fresh()->posicao_final);

        // Com as entradas fechadas, entrada não se desfaz mais.
        $this->assertRegra('As entradas já foram fechadas: o Buy-in de Ana não se desfaz mais.', fn () => $this->desfazer($ana));
    }

    public function test_pede_motivo_e_so_o_admin_desfaz(): void
    {
        $joao = $this->jogador('João');
        $this->entrar($joao, TipoItem::Buyin);

        $this->assertRegra('Diga o motivo para desfazer.', fn () => $this->desfazer($joao, '   '));

        $this->expectException(AuthorizationException::class);
        app(DesfazerAcao::class)($joao, 'Errei', $this->operador);
    }
}
