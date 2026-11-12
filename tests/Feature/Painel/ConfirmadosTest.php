<?php

namespace Tests\Feature\Painel;

use App\Actions\Jogo\IniciarJogo;
use App\Actions\Jogo\TrocarLugar;
use App\Enums\DirecaoPagamento;
use App\Enums\FormaPagamento;
use App\Enums\TipoItem;
use App\Filament\Resources\Jogos\Pages\EditJogo;
use App\Filament\Resources\Jogos\RelationManagers\ConfirmadosRelationManager;
use App\Filament\Resources\Jogos\RelationManagers\HistoricoRelationManager;
use App\Models\Jogo;
use App\Models\Participante;
use App\Models\Seguranca;
use App\Models\Usuario;
use App\Services\Jogo\Saldos;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\UsaPainel;
use Tests\TestCase;

/** Pré-torneio no painel (aba Confirmados do jogo): a lista e os pagamentos antecipados. */
class ConfirmadosTest extends TestCase
{
    use RefreshDatabase;
    use UsaPainel;

    private Seguranca $conta;

    private Jogo $jogo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->conta = $this->entrarNoPainel('Admin');
        $this->jogo = Jogo::factory()->agendado()->comEntradasPadrao()->create(['nome' => 'Poker Night #20']);
    }

    private function aba(?Jogo $jogo = null)
    {
        return Livewire::test(ConfirmadosRelationManager::class, ['ownerRecord' => $jogo ?? $this->jogo, 'pageClass' => EditJogo::class]);
    }

    public function test_confirmar_cadastrar_e_tirar_da_lista(): void
    {
        $ana = Usuario::factory()->create(['nome' => 'Ana', 'apelido' => null]);
        $beto = Usuario::factory()->create(['nome' => 'Beto', 'apelido' => null]);

        $this->aba()->callAction(TestAction::make('confirmar')->table(), data: ['usuarios' => [$ana->id, $beto->id]])
            ->assertNotified('2 jogadores confirmados.');

        $this->aba()->callAction(TestAction::make('novo')->table(), data: ['nome' => 'Caio Novo', 'apelido' => 'Caio', 'telefone' => '(11) 99999-0000'])
            ->assertNotified('Caio Novo cadastrado e confirmado.');

        $caio = Usuario::query()->where('nome', 'Caio Novo')->sole();
        $this->assertSame('(11) 99999-0000', $caio->telefone);
        $this->assertSame(3, $this->jogo->participantes()->count());

        $doBeto = $this->jogo->participantes()->where('usuario_id', $beto->id)->sole();
        $this->aba()
            ->assertCanSeeTableRecords($this->jogo->participantes()->get())
            ->callAction(TestAction::make('tirar')->table($doBeto));

        $this->assertSame(2, $this->jogo->participantes()->count());
    }

    public function test_pagamento_antecipado_abate_do_buyin_e_quem_nao_veio_fica_com_credito(): void
    {
        $mesa = $this->jogo->mesas()->create(['nome' => 'Mesa 1', 'lugares' => 9, 'ordem' => 1]);
        [$ana, $beto, $caio] = collect(['Ana', 'Beto', 'Caio'])
            ->map(fn (string $nome) => $this->jogo->participantes()->create(['usuario_id' => Usuario::factory()->create(['nome' => $nome, 'apelido' => null])->id]))
            ->all();

        // Ana e Caio pagaram o buy-in antes, pelo painel; o valor vem preenchido com o buy-in.
        foreach ([$ana, $caio] as $quem) {
            $this->aba()
                ->mountAction(TestAction::make('pagamento')->table($quem))
                ->assertActionDataSet(['valor' => '100', 'direcao' => DirecaoPagamento::JogadorPagou->value, 'forma' => FormaPagamento::Pix->value])
                ->callMountedAction()
                ->assertNotified("{$quem->nome()} pagou R$ 100.");
        }

        $pagamento = $ana->pagamentos()->sole();
        $this->assertSame([null, $this->conta->id, 'Pago antes do torneio'], [$pagamento->registrado_por, $pagamento->registrado_no_painel_por, $pagamento->observacao]);

        // No dia: Ana e Beto sentam; Caio não veio.
        $admin = Usuario::factory()->foraDaNoite()->create();
        $admin->assignRole('Admin');
        app(TrocarLugar::class)($ana, $mesa, 1, $admin);
        app(TrocarLugar::class)($beto, $mesa, 2, $admin);
        app(IniciarJogo::class)($this->jogo, $admin);

        $saldos = app(Saldos::class)->doJogo($this->jogo);
        $this->assertTrue($saldos[$ana->id]->quitado(), 'Ana entrou sem dever: já tinha pago');
        $this->assertSame(10000, $saldos[$beto->id]->saldo);
        $this->assertSame(-10000, $saldos[$caio->id]->saldo, 'Caio não entrou: o caixa devolve');
        $this->assertSame(0, $caio->entradas()->count());

        $this->aba($this->jogo->fresh())->assertSee('Não veio · devolver R$ 100');
    }

    public function test_desfazer_pagamento_e_o_historico_no_painel(): void
    {
        $ana = $this->jogo->participantes()->create(['usuario_id' => Usuario::factory()->create(['nome' => 'Ana', 'apelido' => null])->id]);
        $this->aba()->callAction(TestAction::make('pagamento')->table($ana), data: ['valor' => '100', 'direcao' => 'jogador_pagou', 'forma' => 'pix', 'observacao' => 'Buy-in']);
        $pagamento = $ana->pagamentos()->sole();

        $this->aba()
            ->callAction(TestAction::make('desfazerPagamento')->table($ana), data: ['pagamento' => $pagamento->id, 'motivo' => 'Era da Bia'])
            ->assertNotified('Pagamento de Ana desfeito.');

        $this->assertNotNull($pagamento->fresh()->cancelado_em);
        $this->aba()->assertActionHidden(TestAction::make('desfazerPagamento')->table($ana));

        Livewire::test(HistoricoRelationManager::class, ['ownerRecord' => $this->jogo, 'pageClass' => EditJogo::class])
            ->assertCanSeeTableRecords($this->jogo->alteracoes()->get())
            ->assertSee(['Pagamento desfeito', 'Desfez: Ana pagou R$ 100 (pix)', 'Era da Bia', $this->conta->login.' (painel)']);
    }

    public function test_geral_ve_a_lista_mas_nao_lanca_pagamento(): void
    {
        $this->entrarNoPainel('Geral');
        $ana = $this->jogo->participantes()->create(['usuario_id' => Usuario::factory()->create(['nome' => 'Ana'])->id]);

        $this->aba()
            ->assertCanSeeTableRecords([$ana])
            ->assertActionHidden(TestAction::make('pagamento')->table($ana))
            ->assertActionHidden(TestAction::make('confirmar')->table());
    }

    public function test_rascunho_nao_tem_lista(): void
    {
        $rascunho = Jogo::factory()->create();

        $this->assertFalse(ConfirmadosRelationManager::canViewForRecord($rascunho, EditJogo::class));
        $this->assertTrue(ConfirmadosRelationManager::canViewForRecord($this->jogo, EditJogo::class));
    }

    public function test_participante_de_outro_jogo_nao_aparece(): void
    {
        $outro = Jogo::factory()->agendado()->create();
        $deOutro = $outro->participantes()->create(['usuario_id' => Usuario::factory()->create()->id]);
        $daqui = $this->jogo->participantes()->create(['usuario_id' => Usuario::factory()->create()->id]);

        $this->aba()->assertCanSeeTableRecords([$daqui])->assertCanNotSeeTableRecords([$deOutro]);
        $this->assertInstanceOf(Participante::class, $daqui);
    }

    public function test_marca_quem_pagou_antes_e_o_bonus_entra_no_buyin(): void
    {
        $this->jogo->update(['antecipado_fichas' => 20000]);
        $mesa = $this->jogo->mesas()->create(['nome' => 'Mesa 1', 'lugares' => 9, 'ordem' => 1]);
        [$ana, $beto] = collect(['Ana', 'Beto'])
            ->map(fn (string $nome) => $this->jogo->participantes()->create(['usuario_id' => Usuario::factory()->create(['nome' => $nome, 'apelido' => null])->id]))
            ->all();

        // Ana paga o buy-in antes e já ganha o bônus no mesmo modal; Beto é marcado depois, pela linha.
        $this->aba()
            ->callAction(TestAction::make('pagamento')->table($ana), data: ['antecipado' => true])
            ->assertNotified('Ana pagou R$ 100 e ganha 20.000 fichas a mais.');

        $this->aba()
            ->callAction(TestAction::make('antecipado')->table($beto))
            ->assertNotified('Beto pagou antes: ganha 20.000 fichas a mais ao sentar.')
            ->assertSee('Pagou antes · +20.000');

        $this->assertSame([true, true], [$ana->fresh()->pagou_antes, $beto->fresh()->pagou_antes]);

        // Tirar a marca do Beto; no dia, os dois sentam e o torneio começa.
        $this->aba()->callAction(TestAction::make('antecipado')->table($beto))
            ->assertNotified('Beto sem o pagamento antecipado.');

        $admin = Usuario::factory()->foraDaNoite()->create();
        $admin->assignRole('Admin');
        app(TrocarLugar::class)($ana, $mesa, 1, $admin);
        app(TrocarLugar::class)($beto, $mesa, 2, $admin);
        app(IniciarJogo::class)($this->jogo, $admin);

        $this->assertSame(20000, $ana->entradas()->sole()->antecipado);
        $this->assertSame(0, $beto->entradas()->sole()->antecipado);

        // Fica no histórico do jogo.
        Livewire::test(HistoricoRelationManager::class, ['ownerRecord' => $this->jogo, 'pageClass' => EditJogo::class])
            ->assertSee(['Ana pagou antes: ganha 20.000 fichas ao sentar', 'Beto sem o pagamento antecipado']);
    }

    public function test_sem_bonus_no_jogo_nao_tem_a_marca(): void
    {
        $ana = $this->jogo->participantes()->create(['usuario_id' => Usuario::factory()->create(['nome' => 'Ana', 'apelido' => null])->id]);

        $this->aba()
            ->assertActionHidden(TestAction::make('antecipado')->table($ana))
            ->mountAction(TestAction::make('pagamento')->table($ana))
            ->assertFormFieldHidden('antecipado');
    }

    public function test_geral_nao_marca_pagou_antes(): void
    {
        $this->jogo->update(['antecipado_fichas' => 20000]);
        $ana = $this->jogo->participantes()->create(['usuario_id' => Usuario::factory()->create(['nome' => 'Ana', 'apelido' => null])->id]);
        $this->entrarNoPainel('Geral');

        $this->aba()->assertActionHidden(TestAction::make('antecipado')->table($ana));
    }

    public function test_opcionais_pagos_antes_entram_junto_do_buyin(): void
    {
        $ana = $this->jogo->participantes()->create(['usuario_id' => Usuario::factory()->create(['nome' => 'Ana', 'apelido' => null])->id]);

        // Sem opcional pago antes no cadastro, não tem a marca.
        $this->aba()
            ->assertActionHidden(TestAction::make('opcionaisPagos')->table($ana))
            ->mountAction(TestAction::make('pagamento')->table($ana))
            ->assertFormFieldHidden('opcionais_pagos');

        $pizza = $this->jogo->itens()->create([
            'tipo' => TipoItem::Opcional, 'nome' => 'Taxa Comida', 'valor' => 40, 'fichas' => 40000,
            'compravel_com' => ['jogador'], 'percentual_premiacao' => 0, 'pago_antes' => true,
        ]);
        $mesa = $this->jogo->mesas()->create(['nome' => 'Mesa 1', 'lugares' => 9, 'ordem' => 1]);
        $beto = $this->jogo->participantes()->create(['usuario_id' => Usuario::factory()->create(['nome' => 'Beto', 'apelido' => null])->id]);

        // Ana pela linha (Opcionais pagos); Beto junto do Pagamento.
        $this->aba()
            ->callAction(TestAction::make('opcionaisPagos')->table($ana), data: ['opcionais' => [(string) $pizza->id]])
            ->assertNotified('Ana pagou Taxa Comida antes: entra junto do buy-in, com 40.000 fichas.')
            ->assertSee('Opcionais pagos antes');

        $this->aba()
            ->callAction(TestAction::make('pagamento')->table($beto), data: ['valor' => '140', 'opcionais_pagos' => [(string) $pizza->id]])
            ->assertNotified('Beto pagou R$ 140 (pago antes: Taxa Comida).');

        // No dia, os dois sentam, o torneio começa e o buy-in já leva a pizza, com as fichas.
        $admin = Usuario::factory()->foraDaNoite()->create();
        $admin->assignRole('Admin');
        app(TrocarLugar::class)($ana, $mesa, 1, $admin);
        app(TrocarLugar::class)($beto, $mesa, 2, $admin);
        app(IniciarJogo::class)($this->jogo, $admin);

        foreach ([$ana, $beto] as $participante) {
            $buyin = $participante->entradas()->validas()->where('tipo', TipoItem::Buyin)->sole();
            $this->assertSame([$pizza->id], $buyin->opcionais()->validas()->pluck('jogo_item_id')->all());
        }

        // Desmarcar pela linha, com o jogo em andamento: sai do buy-in.
        $this->aba()
            ->callAction(TestAction::make('opcionaisPagos')->table($ana->fresh()), data: ['opcionais' => []])
            ->assertNotified('Ana não pagou Taxa Comida antes: saiu do buy-in (recolher 40.000 fichas).');

        // O Geral não marca.
        $this->entrarNoPainel('Geral');
        $this->aba()->assertActionHidden(TestAction::make('opcionaisPagos')->table($ana));
    }
}
