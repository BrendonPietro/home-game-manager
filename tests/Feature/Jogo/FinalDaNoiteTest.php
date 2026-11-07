<?php

namespace Tests\Feature\Jogo;

use App\Actions\Jogo\AbrirIntervalo;
use App\Actions\Jogo\DefinirResultado;
use App\Actions\Jogo\FecharEntradas;
use App\Actions\Jogo\FinalizarJogo;
use App\Actions\Jogo\MarcarAcerto;
use App\Actions\Jogo\RegistrarCashOut;
use App\Actions\Jogo\RegistrarPagamento;
use App\Enums\DirecaoPagamento;
use App\Enums\FormaPagamento;
use App\Enums\Periodicidade;
use App\Enums\StatusJogo;
use App\Enums\TipoItem;
use App\Models\Jogo;
use App\Models\Participante;
use App\Models\Ranking;
use App\Services\Jogo\Saldos;
use App\Support\Dinheiro;
use App\Support\EtapaDoJogo;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\MontaNoite;
use Tests\TestCase;

class FinalDaNoiteTest extends TestCase
{
    use MontaNoite;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->montarNoite();
    }

    /** @return list<Participante> com buy-in de R$ 100 */
    private function comBuyin(string ...$nomes): array
    {
        return array_map(function (string $nome) {
            $jogador = $this->jogador($nome);
            $this->entrar($jogador, TipoItem::Buyin);

            return $jogador;
        }, $nomes);
    }

    private function fecharEntradas(): void
    {
        app(AbrirIntervalo::class)($this->jogo, $this->admin);
        app(FecharEntradas::class)($this->jogo, $this->admin);
    }

    private function definir(array $posicoes): void
    {
        app(DefinirResultado::class)($this->jogo, $posicoes, $this->admin);
    }

    private function finalizar(?Jogo $jogo = null): Jogo
    {
        return app(FinalizarJogo::class)($jogo ?? $this->jogo, $this->admin);
    }

    public function test_definir_o_resultado_de_quem_esta_em_jogo(): void
    {
        [$ana, $beto, $caio, $dudu] = $this->comBuyin('Ana', 'Beto', 'Caio', 'Dudu');

        $this->assertRegra('Feche as entradas antes de definir o resultado.', fn () => $this->definir([]));

        $this->fecharEntradas();
        $this->eliminar($ana); // 4º

        $this->assertRegra('Defina a posição de todos que ainda estão em jogo.', fn () => $this->definir([$beto->id => 1, $caio->id => 2]));
        $this->assertRegra('Quem está em jogo fica do 1º ao 3º, sem repetir.', fn () => $this->definir([$beto->id => 1, $caio->id => 1, $dudu->id => 2]));

        $this->definir([$beto->id => '2', $caio->id => '3', $dudu->id => '1']);

        // 4 × R$ 100 pela regra de até 10 jogadores (65/35).
        $this->assertSame(
            [[4, null], [2, '140.00'], [3, null], [1, '260.00']],
            array_map(fn (Participante $p) => [$p->fresh()->posicao_final, $p->fresh()->premio], [$ana, $beto, $caio, $dudu]),
        );
        $this->assertRegra('O resultado já está definido.', fn () => $this->definir([$dudu->id => 1]));
    }

    public function test_so_o_admin_define_e_cash_game_nao_tem_posicao(): void
    {
        $cash = Jogo::factory()->cashGame()->emAndamento()->create();
        $this->assertRegra('Cash game não tem posições: o resultado sai dos cash-outs.', fn () => app(DefinirResultado::class)($cash, [], $this->admin));

        $this->expectException(AuthorizationException::class);
        app(DefinirResultado::class)($this->jogo, [], $this->operador);
    }

    public function test_finalizar_torneio_grava_acertos_e_pontos(): void
    {
        $ranking = Ranking::query()->create([
            'nome' => 'Anual', 'periodicidade' => Periodicidade::Anual, 'ft_pontua' => true, 'pontos_mesa_final' => 2,
            'participacao_pontua' => true, 'pontos_participacao' => 1,
        ]);
        $ranking->pontuacoes()->createMany([['posicao' => 1, 'pontos' => 10], ['posicao' => 2, 'pontos' => 6]]);
        $this->jogo->rankings()->attach($ranking);
        $this->jogo->update(['tamanho_mesa_final' => 2]);

        [$ana, $beto, $caio] = $this->comBuyin('Ana', 'Beto', 'Caio');
        $this->pagar($ana, 10000);

        $this->assertRegra('Feche as entradas antes de finalizar.', fn () => $this->finalizar());

        $this->fecharEntradas();
        $this->assertRegra('Falta a posição de Ana, Beto e Caio.', fn () => $this->finalizar());

        $this->eliminar($caio); // 3º; Ana e Beto decidem: Ana 1º
        $this->definir([$ana->id => 1, $beto->id => 2]);

        $jogo = $this->finalizar();

        $this->assertSame(StatusJogo::Finalizado, $jogo->status);
        $this->assertNotNull($jogo->finalizado_em);

        // R$ 300 → prêmios 195 / 105. Ana: 100 − 195 − 100 pago = recebe 195. Beto: 100 − 105 = recebe 5.
        // Caio deve 100. No caixa entrou R$ 100 da Ana: a casa tem 100 e deve 100 (o caixa paga).
        $acertos = $jogo->acertos()->get()->map(fn ($a) => [$a->de_participante_id, $a->para_participante_id, Dinheiro::centavos($a->valor)])->all();
        $this->assertEqualsCanonicalizing([[$caio->id, $ana->id, 10000], [null, $ana->id, 9500], [null, $beto->id, 500]], $acertos);

        // Pontos: Ana 10 + 2 (mesa final) + 1, Beto 6 + 2 + 1, Caio só a participação.
        $this->assertSame(
            [$ana->usuario_id => 13, $beto->usuario_id => 9, $caio->usuario_id => 1],
            $ranking->pontos()->pluck('pontos', 'usuario_id')->all(),
        );
        $this->assertRegra('Só jogo em andamento pode ser finalizado.', fn () => $this->finalizar());
    }

    public function test_finalizar_cash_game_pede_o_cash_out_de_todos(): void
    {
        $cash = Jogo::factory()->cashGame()->emAndamento()->comEntradasPadrao()->create();
        $mesa = $cash->mesas()->create(['mesa_id' => $this->mesa->mesa_id, 'lugares' => 9]);
        $jogadores = collect(['Ana', 'Beto'])->map(function (string $nome, int $i) use ($cash, $mesa) {
            $p = $cash->participantes()->create(['usuario_id' => $this->jogador($nome)->usuario_id, 'jogo_mesa_id' => $mesa->id, 'lugar' => $i + 1]);
            $p->forceFill(['status' => 'jogando'])->save();
            $this->entrar($p, $cash->itens()->where('tipo', TipoItem::Buyin)->first());

            return $p;
        });

        $this->assertRegra('Falta o cash-out de Ana e Beto.', fn () => $this->finalizar($cash));

        // Ficha de R$ 0,01: Ana sai com R$ 150, Beto com R$ 50.
        app(RegistrarCashOut::class)($jogadores[0], 15000, $this->operador);
        app(RegistrarCashOut::class)($jogadores[1], 5000, $this->operador);

        $this->finalizar($cash);

        $this->assertSame(
            [[$jogadores[1]->id, $jogadores[0]->id, 5000]],
            $cash->acertos()->get()->map(fn ($a) => [$a->de_participante_id, $a->para_participante_id, Dinheiro::centavos($a->valor)])->all(),
        );
    }

    public function test_etapas_finais_abrem_com_as_entradas_fechadas(): void
    {
        $this->comBuyin('Ana', 'Beto');
        $this->assertSame(['ao-vivo', 'resumo'], EtapaDoJogo::permitidas($this->jogo->fresh()));

        $this->fecharEntradas();
        $this->assertSame(['ao-vivo', 'resumo', 'premiacao', 'resultado', 'fechamento'], EtapaDoJogo::permitidas($this->jogo->fresh()));

        // Cash game não tem premiação nem precisa fechar entradas.
        $cash = Jogo::factory()->cashGame()->emAndamento()->create();
        $this->assertSame(['ao-vivo', 'resumo', 'resultado', 'fechamento'], EtapaDoJogo::permitidas($cash));
    }

    public function test_acerto_feito_vira_pagamentos_e_fecha_os_saldos(): void
    {
        [$ana, $beto, $caio] = $this->comBuyin('Ana', 'Beto', 'Caio');
        $this->pagar($ana, 10000);
        $this->fecharEntradas();
        $this->eliminar($caio);
        $this->definir([$ana->id => 1, $beto->id => 2]);

        $acerto = $this->jogo->acertos()->create(['valor' => 1]); // jogo ainda não finalizado
        $this->assertRegra('Os acertos se marcam depois de finalizar.', fn () => app(MarcarAcerto::class)($acerto, FormaPagamento::Pix, $this->operador));
        $acerto->delete();

        $this->finalizar();

        // Caio → Ana R$ 100, Caixa → Ana R$ 95, Caixa → Beto R$ 5 (ver o teste de finalizar).
        foreach ($this->jogo->acertos()->get() as $acerto) {
            app(MarcarAcerto::class)($acerto, FormaPagamento::Dinheiro, $this->operador);
        }

        $saldos = app(Saldos::class)->doJogo($this->jogo);
        $this->assertTrue($saldos->every(fn ($s) => $s->quitado()));
        $this->assertSame(0, $this->jogo->acertos()->where('pago', false)->count());
        $this->assertSame('Acerto: pagou a Ana', $caio->pagamentos()->sole()->observacao);
        $this->assertSame(3, $ana->pagamentos()->count()); // o pix da noite e os recebimentos do Caio e do caixa

        $this->assertRegra('Esse acerto já foi marcado como feito.', fn () => app(MarcarAcerto::class)($this->jogo->acertos()->first(), FormaPagamento::Pix, $this->operador));
    }

    public function test_pendencia_paga_pelo_painel_refaz_os_acertos_e_nao_cobra_duas_vezes(): void
    {
        [$ana, $beto, $caio] = $this->comBuyin('Ana', 'Beto', 'Caio');
        $this->pagar($ana, 10000);
        $this->fecharEntradas();
        $this->eliminar($caio);
        $this->definir([$ana->id => 1, $beto->id => 2]);
        $this->finalizar();

        $doCaio = $this->jogo->acertos()->where('de_participante_id', $caio->id)->sole();
        $this->assertSame(10000, Dinheiro::centavos($doCaio->valor));

        // Caio acerta pelo painel dele (pix para o caixa), não pelo FEITO do acerto.
        $this->pagar($caio, 10000);

        $this->assertNull($doCaio->fresh(), 'o acerto antigo do Caio sai da lista');
        $this->assertSame(0, $this->jogo->acertos()->where('de_participante_id', $caio->id)->count());
        $this->assertSame(0, app(Saldos::class)->doParticipante($caio->fresh())->saldo);

        // FEITO no acerto que estava na tela de outro aparelho: avisa e não lança nada.
        $this->assertRegra(MarcarAcerto::MUDOU, fn () => app(MarcarAcerto::class)($doCaio, FormaPagamento::Pix, $this->operador));
        $this->assertSame(1, $caio->pagamentos()->count());

        // Os que sobraram fecham a conta: o caixa paga Ana e Beto.
        foreach ($this->jogo->acertos()->where('pago', false)->get() as $acerto) {
            $this->assertNull($acerto->de_participante_id);
            app(MarcarAcerto::class)($acerto, FormaPagamento::Pix, $this->operador);
        }

        $this->assertTrue(app(Saldos::class)->doJogo($this->jogo)->every(fn ($s) => $s->quitado()));
    }

    public function test_acerto_que_nao_bate_com_os_saldos_e_refeito(): void
    {
        [$ana, $beto, $caio] = $this->comBuyin('Ana', 'Beto', 'Caio');
        $this->fecharEntradas();
        $this->eliminar($caio);
        $this->definir([$ana->id => 1, $beto->id => 2]);
        $this->finalizar();

        // Um pagamento que não passou pela Action (não refez a lista): o acerto do Caio ficou velho.
        $this->jogo->pagamentos()->create([
            'uuid' => (string) Str::uuid(), 'participante_id' => $caio->id, 'direcao' => 'jogador_pagou',
            'valor' => '100.00', 'forma' => 'pix', 'registrado_por' => $this->admin->id,
        ]);
        $velho = $this->jogo->acertos()->where('de_participante_id', $caio->id)->first();

        $this->assertRegra(MarcarAcerto::MUDOU, fn () => app(MarcarAcerto::class)($velho, FormaPagamento::Pix, $this->operador));

        $this->assertNull($velho->fresh());
        $this->assertSame(1, $caio->pagamentos()->count());
        $this->assertSame(0, $this->jogo->acertos()->where('de_participante_id', $caio->id)->count());
    }

    public function test_pagamento_e_acerto_so_em_pix_ou_dinheiro(): void
    {
        [$ana] = $this->comBuyin('Ana');

        $this->assertSame([FormaPagamento::Pix, FormaPagamento::Dinheiro], FormaPagamento::disponiveis());
        $this->assertRegra('Forma de pagamento não aceita: use Pix ou Dinheiro.', fn () => app(RegistrarPagamento::class)(
            $ana, DirecaoPagamento::JogadorPagou, 10000, FormaPagamento::Cartao, $this->operador, (string) Str::uuid(),
        ));
        $this->assertSame(0, $ana->pagamentos()->count());
    }

    public function test_so_o_admin_finaliza(): void
    {
        $this->expectException(AuthorizationException::class);
        app(FinalizarJogo::class)($this->jogo, $this->operador);
    }
}
