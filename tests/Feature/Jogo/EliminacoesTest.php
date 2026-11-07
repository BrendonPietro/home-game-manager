<?php

namespace Tests\Feature\Jogo;

use App\Actions\Jogo\AbrirIntervalo;
use App\Actions\Jogo\AjustarMesa;
use App\Actions\Jogo\FecharEntradas;
use App\Actions\Jogo\RemoverMesa;
use App\Actions\Jogo\TrocarLugar;
use App\Enums\FaseTorneio;
use App\Enums\StatusParticipante;
use App\Enums\TipoItem;
use App\Models\Participante;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MontaNoite;
use Tests\TestCase;

class EliminacoesTest extends TestCase
{
    use MontaNoite;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->montarNoite();
    }

    /** @return list<Participante> jogadores com buy-in */
    private function comBuyin(string ...$nomes): array
    {
        return array_map(function (string $nome) {
            $jogador = $this->jogador($nome);
            $this->entrar($jogador, TipoItem::Buyin);

            return $jogador;
        }, $nomes);
    }

    private function abrirIntervalo(): void
    {
        app(AbrirIntervalo::class)($this->jogo, $this->admin);
    }

    private function fecharEntradas(): void
    {
        app(FecharEntradas::class)($this->jogo, $this->admin);
    }

    public function test_provisoria_nos_rebuys_e_o_rebuy_traz_de_volta(): void
    {
        [$joao] = $this->comBuyin('João', 'Lucas');

        $eliminacao = $this->eliminar($joao);

        $this->assertFalse($eliminacao->definitiva);
        $this->assertSame(StatusParticipante::Eliminado, $joao->fresh()->status);
        $this->assertSame([$this->mesa->id, 1], [$joao->fresh()->jogo_mesa_id, $joao->fresh()->lugar]); // guarda o lugar
        $this->assertRegra('João já está eliminado.', fn () => $this->eliminar($joao));
        $this->assertRegra('João está fora: só volta com rebuy.', fn () => $this->entrar($joao, TipoItem::Buyin));

        $rebuy = $this->entrar($joao, TipoItem::Rebuy);

        $this->assertSame($rebuy->id, $eliminacao->fresh()->voltou_com);
        $this->assertSame(StatusParticipante::Jogando, $joao->fresh()->status);
    }

    public function test_no_intervalo_quem_caiu_ainda_volta_e_fechar_entradas_deixa_definitivo(): void
    {
        [$joao, $lucas, $carlos] = $this->comBuyin('João', 'Lucas', 'Carlos', 'Diego');

        $caiu = $this->eliminar($joao);
        $voltou = $this->eliminar($lucas);
        $this->entrar($lucas, TipoItem::Rebuy);

        $this->abrirIntervalo();

        $jogo = $this->jogo->fresh();
        $this->assertSame(FaseTorneio::Intervalo, $jogo->fase);
        $this->assertNotNull($jogo->intervalo_aberto_em);
        $this->assertFalse($caiu->fresh()->definitiva, 'no intervalo ainda dá para voltar');
        $this->assertRegra('O intervalo já foi aberto.', fn () => $this->abrirIntervalo());

        // Carlos cai no intervalo (provisória) e João, que esperou o intervalo, volta com rebuy.
        $doCarlos = $this->eliminar($carlos);
        $this->assertFalse($doCarlos->definitiva);
        $rebuy = $this->entrar($joao, TipoItem::Rebuy);
        $this->assertSame($rebuy->id, $caiu->fresh()->voltou_com);
        $this->assertSame(StatusParticipante::Jogando, $joao->fresh()->status);

        // Fechar entradas: quem não voltou fica de vez, na ordem em que caiu, e já tem a posição.
        $this->fecharEntradas();

        $this->assertTrue($doCarlos->fresh()->definitiva);
        $this->assertFalse($voltou->fresh()->definitiva);
        $this->assertSame(4, $carlos->fresh()->posicao_final);
        $this->assertRegra('As entradas já foram fechadas.', fn () => $this->entrar($carlos, TipoItem::Rebuy));
    }

    public function test_fechar_entradas_grava_os_premios_e_as_posicoes(): void
    {
        [$ana, $beto, $caio, $dudu] = $this->comBuyin('Ana', 'Beto', 'Caio', 'Dudu');

        $this->assertRegra('Abra o intervalo antes de fechar as entradas.', fn () => $this->fecharEntradas());

        $this->eliminar($ana);
        $this->abrirIntervalo();
        $this->eliminar($caio);

        $this->fecharEntradas();

        // 4 jogadores × R$ 100, sem taxa: R$ 400 pela regra de até 10 jogadores (65/35).
        $jogo = $this->jogo->fresh();
        $this->assertSame(FaseTorneio::SemEntradas, $jogo->fase);
        $this->assertNotNull($jogo->entradas_fechadas_em);
        $this->assertSame([1 => '260.00', 2 => '140.00'], $jogo->premiacoes()->pluck('valor', 'posicao')->all());
        $this->assertSame([4, 3], [$ana->fresh()->posicao_final, $caio->fresh()->posicao_final]);
        $this->assertNull($ana->fresh()->premio);
        $this->assertRegra('As entradas já estão fechadas.', fn () => $this->fecharEntradas());

        // Depois de fechar, cada eliminação dá a posição na hora; quem sobra é o 1º.
        $this->eliminar($beto);

        $this->assertSame([2, '140.00'], [$beto->fresh()->posicao_final, $beto->fresh()->premio]);
        $this->assertSame([1, '260.00'], [$dudu->fresh()->posicao_final, $dudu->fresh()->premio]);
        $this->assertRegra('Dudu é o último na mesa.', fn () => $this->eliminar($dudu));
    }

    public function test_fechar_entradas_pede_dois_jogadores_com_buyin(): void
    {
        $this->comBuyin('João');
        $this->jogador('Lucas');
        $this->abrirIntervalo();

        $this->assertRegra('Fechar entradas pede pelo menos 2 jogadores com buy-in.', fn () => $this->fecharEntradas());
    }

    public function test_sem_buyin_nao_e_eliminado(): void
    {
        $this->comBuyin('João');

        $this->assertRegra('Zeca não tem buy-in.', fn () => $this->eliminar($this->jogador('Zeca')));
    }

    public function test_sentado_sem_buyin_nao_conta_para_o_ultimo_na_mesa(): void
    {
        [$joao] = $this->comBuyin('João');
        $this->jogador('Zeca'); // jogando, mas sem buy-in

        $this->assertRegra('João é o último na mesa.', fn () => $this->eliminar($joao));
    }

    public function test_quem_saiu_so_guarda_o_lugar(): void
    {
        [$joao, $lucas, $zeca] = $this->comBuyin('João', 'Lucas', 'Zeca');
        app(TrocarLugar::class)($lucas, $this->mesa, 8, $this->admin);
        $this->eliminar($joao);
        $this->eliminar($lucas);

        // Sentar no lugar de quem caiu não troca: quem caiu fica sem lugar.
        app(TrocarLugar::class)($zeca, $this->mesa, 1, $this->admin);
        $this->assertSame([$this->mesa->id, 1], [$zeca->fresh()->jogo_mesa_id, $zeca->fresh()->lugar]);
        $this->assertSame([null, null], [$joao->fresh()->jogo_mesa_id, $joao->fresh()->lugar]);

        // Quem chega atrasado e senta com o jogo em andamento passa a jogar.
        $atrasado = $this->jogo->participantes()->create(['usuario_id' => Usuario::factory()->create()->id]);
        app(TrocarLugar::class)($atrasado, $this->mesa, 5, $this->admin);
        $this->assertSame(StatusParticipante::Jogando, $atrasado->fresh()->status);

        // Diminuir a mesa tira quem caiu do lugar 8; tirar a mesa ainda pede que ninguém esteja jogando.
        app(AjustarMesa::class)($this->mesa, 6, null, $this->admin);
        $this->assertSame([null, null], [$lucas->fresh()->jogo_mesa_id, $lucas->fresh()->lugar]);
        $this->assertRegra('Tire os jogadores da Mesa 01 antes de removê-la.', fn () => app(RemoverMesa::class)($this->mesa, $this->admin));
    }
}
