<?php

namespace Tests\Feature\Jogo;

use App\Actions\Jogo\AbrirIntervalo;
use App\Actions\Jogo\DesfazerAcerto;
use App\Actions\Jogo\DesfazerPagamento;
use App\Actions\Jogo\FecharEntradas;
use App\Actions\Jogo\MarcarAcerto;
use App\Actions\Jogo\RegistrarPagamento;
use App\Actions\Jogo\VoltarFase;
use App\Enums\DirecaoPagamento;
use App\Enums\FaseTorneio;
use App\Enums\FormaPagamento;
use App\Enums\StatusParticipante;
use App\Enums\TipoAlteracao;
use App\Enums\TipoItem;
use App\Models\Jogo;
use App\Models\Seguranca;
use App\Models\Usuario;
use App\Services\Jogo\Saldos;
use App\Support\Dinheiro;
use Database\Seeders\RegrasPremiacaoSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\MontaNoite;
use Tests\TestCase;

/** Correções com registro: voltar a fase, desfazer pagamento e acerto depois de finalizar, e o histórico. */
class CorrecoesTest extends TestCase
{
    use MontaNoite;
    use RefreshDatabase;

    private function voltar(string $motivo = 'Apertei antes da hora'): void
    {
        app(VoltarFase::class)($this->jogo, $motivo, $this->admin);
    }

    public function test_voltar_aos_rebuys_fecha_o_addon_e_fica_no_historico(): void
    {
        $this->montarNoite();
        [$ana] = [$this->jogador('Ana'), $this->jogador('Beto')];
        $this->entrar($ana, TipoItem::Buyin);

        $this->assertRegra('O torneio já está na primeira fase.', fn () => $this->voltar());

        app(AbrirIntervalo::class)($this->jogo, $this->admin);
        $this->assertRegra('Diga o motivo para voltar a fase.', fn () => $this->voltar('  '));
        $this->voltar();

        $jogo = $this->jogo->fresh();
        $this->assertSame([FaseTorneio::Rebuys, null], [$jogo->fase, $jogo->intervalo_aberto_em]);
        $this->assertRegra('Add-on só no intervalo: aperte ABRIR INTERVALO.', fn () => $this->entrar($ana, TipoItem::Addon));

        $ultima = $jogo->alteracoes()->first();
        $this->assertSame([TipoAlteracao::VoltouAosRebuys, 'Apertei antes da hora', $this->admin->id], [$ultima->tipo, $ultima->motivo, $ultima->usuario_id]);
        $this->assertSame(TipoAlteracao::AbriuIntervalo, $jogo->alteracoes()->skip(1)->first()->tipo);
    }

    public function test_reabrir_entradas_devolve_rebuy_e_refaz_posicoes_e_premios(): void
    {
        $this->montarNoite();
        $this->seed(RegrasPremiacaoSeeder::class);
        [$ana, $beto, $caio] = [$this->jogador('Ana'), $this->jogador('Beto'), $this->jogador('Caio')];
        foreach ([$ana, $beto, $caio] as $jogador) {
            $this->entrar($jogador, TipoItem::Buyin);
        }

        $this->eliminar($caio); // caiu nos rebuys e não voltou
        app(AbrirIntervalo::class)($this->jogo, $this->admin);
        app(FecharEntradas::class)($this->jogo, $this->admin);
        $this->eliminar($beto);

        $this->assertSame([3, 2], [$caio->fresh()->posicao_final, $beto->fresh()->posicao_final]);
        $this->assertSame(2, $this->jogo->premiacoes()->count());

        // Chegou um atrasado: reabre as entradas.
        $this->voltar('Chegou o Dudu atrasado');

        $jogo = $this->jogo->fresh();
        $this->assertSame([FaseTorneio::Intervalo, null], [$jogo->fase, $jogo->entradas_fechadas_em]);
        $this->assertSame(0, $jogo->premiacoes()->count(), 'pela regra do clube: refeitos ao fechar de novo');
        $this->assertSame([null, null, null], [$ana->fresh()->posicao_final, $beto->fresh()->posicao_final, $caio->fresh()->posicao_final]);
        $this->assertSame(0, $jogo->eliminacoes()->validas()->definitivas()->count());

        $reabriu = $jogo->alteracoes()->first();
        $this->assertSame(TipoAlteracao::ReabriuEntradas, $reabriu->tipo);
        $this->assertSame([$ana->id => 1, $beto->id => 2, $caio->id => 3], $reabriu->dados['posicoes']); // guardadas para conferir

        // Caio volta com rebuy, Dudu entra e as entradas fecham de novo com 4 jogadores.
        $this->entrar($caio, TipoItem::Rebuy);
        $this->entrar($this->jogador('Dudu'), TipoItem::Buyin);
        app(FecharEntradas::class)($this->jogo, $this->admin);

        $this->assertSame(StatusParticipante::Jogando, $caio->fresh()->status);
        $this->assertSame(4, $beto->fresh()->posicao_final, 'Beto caiu antes e não voltou');
        $this->assertNull($caio->fresh()->posicao_final);
    }

    public function test_operador_nao_volta_fase(): void
    {
        $this->montarNoite();
        app(AbrirIntervalo::class)($this->jogo, $this->admin);

        $this->expectException(AuthorizationException::class);
        app(VoltarFase::class)($this->jogo, 'Quero', $this->operador);
    }

    public function test_desfazer_pagamento_depois_de_finalizar_refaz_os_acertos(): void
    {
        [$ana, , $caio] = $this->noiteFinalizadaDeTres();

        // Caio acertou pelo painel dele, mas foi lançado em dobro.
        $this->pagar($caio, 10000);
        $emDobro = $this->pagar($caio, 10000);
        $this->assertSame(-10000, app(Saldos::class)->doParticipante($caio->fresh())->saldo);

        $this->assertRegra('Diga o motivo para desfazer.', fn () => app(DesfazerPagamento::class)($emDobro, '', $this->admin));
        app(DesfazerPagamento::class)($emDobro, 'Lançado em dobro', $this->admin);

        $this->assertNotNull($emDobro->fresh()->cancelado_em);
        $this->assertSame('Lançado em dobro', $emDobro->fresh()->motivo_cancelamento);
        $this->assertTrue(app(Saldos::class)->doParticipante($caio->fresh())->quitado());
        $this->assertSame(0, $this->jogo->acertos()->where('pago', false)->where('de_participante_id', $caio->id)->count());
        $this->assertSame(0, $this->jogo->acertos()->where('pago', false)->where('para_participante_id', $caio->id)->count());
        $this->assertRegra('Esse pagamento já foi desfeito.', fn () => app(DesfazerPagamento::class)($emDobro, 'De novo', $this->admin));

        $alteracao = $this->jogo->alteracoes()->first();
        $this->assertSame(TipoAlteracao::DesfezPagamento, $alteracao->tipo);
        $this->assertSame('Desfez: Caio pagou R$ 100 (pix)', $alteracao->descricao);

        // O Operador não corrige depois de finalizar.
        $this->expectException(AuthorizationException::class);
        app(DesfazerPagamento::class)($ana->pagamentos()->first(), 'Quero', $this->operador);
    }

    public function test_desfazer_acerto_feito_cancela_os_pagamentos_e_reabre_o_acerto(): void
    {
        [$ana, , $caio] = $this->noiteFinalizadaDeTres();
        $doCaio = $this->jogo->acertos()->where('de_participante_id', $caio->id)->sole();

        app(MarcarAcerto::class)($doCaio, FormaPagamento::Dinheiro, $this->operador);
        $this->assertTrue(app(Saldos::class)->doParticipante($caio->fresh())->quitado());

        // Pagamento de acerto não sai sozinho: só desfazendo o acerto.
        $doPagamento = $caio->pagamentos()->sole();
        $this->assertSame($doCaio->id, $doPagamento->acerto_id);
        $this->assertRegra('Esse pagamento é de um acerto feito: desfaça o acerto no fechamento.', fn () => app(DesfazerPagamento::class)($doPagamento, 'Errado', $this->admin));

        app(DesfazerAcerto::class)($doCaio->fresh(), 'Marcado sem ter pago', $this->admin);

        $this->assertSame(0, $caio->pagamentos()->validas()->count());
        $this->assertSame(0, $ana->pagamentos()->validas()->whereNotNull('acerto_id')->count());
        $this->assertSame(10000, app(Saldos::class)->doParticipante($caio->fresh())->saldo);
        $this->assertSame(10000, Dinheiro::centavos($this->jogo->acertos()->where('pago', false)->where('de_participante_id', $caio->id)->sole()->valor));

        $tipos = $this->jogo->alteracoes()->pluck('tipo')->take(2)->all();
        $this->assertSame([TipoAlteracao::DesfezAcerto, TipoAlteracao::AcertoFeito], $tipos);
        $this->assertSame('Marcado sem ter pago', $this->jogo->alteracoes()->first()->motivo);
    }

    public function test_historico_da_noite_inteira(): void
    {
        $this->noiteFinalizadaDeTres();

        $this->assertSame(
            [TipoAlteracao::Finalizou, TipoAlteracao::DefiniuResultado, TipoAlteracao::FechouEntradas, TipoAlteracao::AbriuIntervalo],
            $this->jogo->alteracoes()->pluck('tipo')->all(),
        );
        $this->assertSame('Fechou as entradas: 3 jogadores, prize pool R$ 300', $this->jogo->alteracoes()->where('tipo', TipoAlteracao::FechouEntradas)->sole()->descricao);
        $this->assertSame($this->admin->nome_exibicao, $this->jogo->alteracoes()->first()->quem());
    }

    public function test_pelo_painel_desfaz_pagamento_antecipado(): void
    {
        $this->montarNoite(Jogo::factory()->agendado()->comEntradasPadrao()->create());
        $ana = $this->jogo->participantes()->create(['usuario_id' => Usuario::factory()->create(['nome' => 'Ana', 'apelido' => null])->id]);
        $conta = Seguranca::factory()->create();
        $conta->assignRole('Admin');
        $pagamento = app(RegistrarPagamento::class)($ana, DirecaoPagamento::JogadorPagou, 10000, FormaPagamento::Pix, $conta, (string) Str::uuid());

        app(DesfazerPagamento::class)($pagamento, 'Era de outro jogador', $conta);

        $this->assertNotNull($pagamento->fresh()->cancelado_em);
        $this->assertNull($pagamento->fresh()->cancelado_por);
        $alteracao = $this->jogo->alteracoes()->first();
        $this->assertSame([null, $conta->id, $conta->login.' (painel)'], [$alteracao->usuario_id, $alteracao->seguranca_id, $alteracao->quem()]);
    }
}
