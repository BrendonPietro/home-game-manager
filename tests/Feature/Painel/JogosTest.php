<?php

namespace Tests\Feature\Painel;

use App\Enums\CalculoTaxa;
use App\Enums\DestinoItem;
use App\Enums\MetodoPremiacao;
use App\Enums\Modalidade;
use App\Enums\StatusJogo;
use App\Enums\TipoItem;
use App\Filament\Resources\Jogos\JogoResource;
use App\Filament\Resources\Jogos\Pages\CreateJogo;
use App\Filament\Resources\Jogos\Pages\EditJogo;
use App\Filament\Resources\Jogos\Pages\ListJogos;
use App\Models\EstruturaBlinds;
use App\Models\Jogo;
use App\Models\Mesa;
use App\Models\Usuario;
use App\Support\PadroesDoJogo;
use Database\Seeders\EstruturasBlindsSeeder;
use Database\Seeders\PokerNight18Seeder;
use Database\Seeders\RegrasPremiacaoSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\UsaPainel;
use Tests\TestCase;

class JogosTest extends TestCase
{
    use RefreshDatabase, UsaPainel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entrarNoPainel('Admin');
        $this->seed(RegrasPremiacaoSeeder::class);
        Repeater::fake();
    }

    /** Estado do formulário de um torneio de sábado (docs/regras.md), com as entradas padrão preenchidas. */
    private function torneio(array $extra = []): array
    {
        return [
            'modalidade' => Modalidade::Torneio->value,
            'nome' => 'Poker Night #19',
            'data' => '2026-10-11',
            'horario' => '20:00',
            'lugares_por_mesa' => 9,
            'tamanho_mesa_final' => 9,
            'itens_buyin' => [['padrao' => true, 'nome' => 'Buy-in', 'valor' => '100', 'fichas' => 20000, 'limite_por_jogador' => 1, 'ativo' => true]],
            'itens_rebuy' => [
                ['padrao' => true, 'nome' => 'Rebuy', 'valor' => '100', 'fichas' => 20000, 'limite_por_jogador' => null, 'ativo' => true],
                ['padrao' => false, 'nome' => 'Rebuy duplo', 'valor' => '200', 'fichas' => 40000, 'limite_por_jogador' => null, 'ativo' => true],
            ],
            'itens_addon' => [['padrao' => true, 'nome' => 'Add-on', 'valor' => '50', 'fichas' => 20000, 'limite_por_jogador' => 1, 'ativo' => true]],
            'opcionais' => [
                ['nome' => 'Opcional', 'valor' => '10', 'fichas' => 20000, 'compravel_com' => 'entrada', 'destino' => 'metade', 'obrigatorio' => false, 'ativo' => true],
                ['nome' => 'Churrasco', 'valor' => '30', 'fichas' => 20000, 'compravel_com' => 'jogador', 'destino' => 'fora', 'obrigatorio' => false, 'ativo' => true],
            ],
            'taxas' => [
                ['nome' => 'Casa', 'calculo' => 'fixo', 'valor' => '190', 'quantidade' => 1],
                ['nome' => 'Dealers', 'calculo' => 'por_unidade', 'valor' => '215', 'quantidade' => 2],
            ],
            'metodo_premiacao' => MetodoPremiacao::Regra->value,
            ...$extra,
        ];
    }

    public function test_lista_mostra_jogos_reais_com_arrecadacao(): void
    {
        $this->seed(PokerNight18Seeder::class);
        $modelo = Jogo::factory()->modelo()->create();
        $noite = Jogo::query()->where('nome', PokerNight18Seeder::NOME)->sole();

        $this->get(JogoResource::getUrl('index'))->assertOk();

        Livewire::test(ListJogos::class)
            ->assertCanSeeTableRecords([$noite])
            ->assertCanNotSeeTableRecords([$modelo])
            ->assertSee('R$ 2.100')
            ->assertSee('Em andamento');
    }

    public function test_cria_torneio_com_entradas_opcionais_taxas_e_mesas(): void
    {
        $mesa = Mesa::factory()->create(['nome' => 'Mesa 01', 'lugares' => 8]);
        $dealer = Usuario::factory()->dealer()->create(['nome' => 'Sérgio']);

        Livewire::test(CreateJogo::class)
            ->fillForm($this->torneio([
                'mesas' => [['mesa_id' => (string) $mesa->id, 'dealer_id' => (string) $dealer->id, 'lugares' => 8]],
            ]))
            ->call('create')
            ->assertHasNoFormErrors();

        $jogo = Jogo::query()->sole();
        $this->assertSame(StatusJogo::Rascunho, $jogo->status);

        $entradas = $jogo->itensDeEntrada()->get();
        $this->assertSame(['Buy-in', 'Rebuy', 'Rebuy duplo', 'Add-on'], $entradas->sortBy(fn ($i) => [$i->tipo->value === 'buyin' ? 0 : ($i->tipo->value === 'rebuy' ? 1 : 2), $i->ordem])->pluck('nome')->values()->all());
        $this->assertSame(3, $entradas->where('padrao', true)->count());
        $this->assertTrue($entradas->every(fn ($i) => $i->destino === DestinoItem::Premiacao));

        $opcional = $jogo->opcionais()->where('nome', 'Opcional')->sole();
        $churrasco = $jogo->opcionais()->where('nome', 'Churrasco')->sole();
        $this->assertSame(DestinoItem::Metade, $opcional->destino);
        $this->assertTrue($opcional->compravelCom(TipoItem::Rebuy));
        $this->assertTrue($churrasco->porJogador());
        $this->assertSame(DestinoItem::Fora, $churrasco->destino);

        $this->assertSame([CalculoTaxa::Fixo, CalculoTaxa::PorUnidade], $jogo->taxas->pluck('calculo')->all());
        $this->assertSame(2, $jogo->taxas[1]->quantidade);

        $this->assertSame($dealer->id, $jogo->mesas->sole()->dealer_id);
        $this->assertSame([$mesa->id], $jogo->evento->mesas->pluck('id')->all());
        $this->assertSame('2026-10-11', $jogo->evento->data->toDateString());
    }

    public function test_premiacao_manual_precisa_somar_100(): void
    {
        Livewire::test(CreateJogo::class)
            ->fillForm($this->torneio(['metodo_premiacao' => 'manual', 'premiacoes' => [['percentual' => '60'], ['percentual' => '30']]]))
            ->call('create')
            ->assertHasFormErrors(['premiacoes'])
            ->assertSee('Os percentuais precisam somar 100% (agora somam 90%).');

        Livewire::test(CreateJogo::class)
            ->fillForm($this->torneio(['metodo_premiacao' => 'manual', 'premiacoes' => [['percentual' => '60'], ['percentual' => '25'], ['percentual' => '15']]]))
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(['60.00', '25.00', '15.00'], Jogo::query()->sole()->premiacoes->pluck('percentual')->all());
        $this->assertSame([1, 2, 3], Jogo::query()->sole()->premiacoes->pluck('posicao')->all());
    }

    public function test_cash_game_pede_valor_da_ficha_e_nao_tem_add_on(): void
    {
        $dados = $this->torneio(['modalidade' => Modalidade::CashGame->value, 'nome' => 'Cash de quarta']);

        Livewire::test(CreateJogo::class)
            ->fillForm($dados)
            ->assertFormFieldIsHidden('itens_addon')
            ->assertFormFieldIsHidden('tamanho_mesa_final')
            ->call('create')
            ->assertHasFormErrors(['valor_ficha' => 'required']);

        Livewire::test(CreateJogo::class)
            ->fillForm([...$dados, 'valor_ficha' => '0.01'])
            ->call('create')
            ->assertHasNoFormErrors();

        $jogo = Jogo::query()->sole();
        $this->assertTrue($jogo->eCashGame());
        $this->assertSame('0.0100', $jogo->valor_ficha);
        $this->assertFalse($jogo->itens()->where('tipo', TipoItem::Addon)->exists());
    }

    public function test_agendar_e_cancelar(): void
    {
        $jogo = Jogo::factory()->comEntradasPadrao()->create();

        Livewire::test(EditJogo::class, ['record' => $jogo->getRouteKey()])
            ->assertActionVisible('agendar')
            ->callAction('agendar');

        $jogo->refresh();
        $this->assertSame(StatusJogo::Agendado, $jogo->status);
        $this->assertNotNull($jogo->evento);

        Livewire::test(EditJogo::class, ['record' => $jogo->getRouteKey()])
            ->assertActionHidden('agendar')
            ->callAction('cancelar');

        $jogo->refresh();
        $this->assertSame(StatusJogo::Cancelado, $jogo->status);
        $this->assertNull($jogo->evento);

        // Cancelado: só ver.
        $this->get(JogoResource::getUrl('edit', ['record' => $jogo]))->assertForbidden();
        $this->get(JogoResource::getUrl('view', ['record' => $jogo]))->assertOk();
    }

    public function test_nao_agenda_sem_buy_in_com_valor(): void
    {
        $jogo = Jogo::factory()->create();

        Livewire::test(EditJogo::class, ['record' => $jogo->getRouteKey()])
            ->callAction('agendar')
            ->assertNotified('O jogo precisa de pelo menos um buy-in ativo com valor.');

        $this->assertSame(StatusJogo::Rascunho, $jogo->fresh()->status);
    }

    public function test_jogo_em_andamento_nao_mexe_na_estrutura_pelo_painel(): void
    {
        $this->seed(PokerNight18Seeder::class);
        $noite = Jogo::query()->where('nome', PokerNight18Seeder::NOME)->sole();
        $itens = $noite->itens()->count();

        Livewire::test(EditJogo::class, ['record' => $noite->getRouteKey()])
            ->assertFormFieldIsDisabled('modalidade')
            ->assertFormFieldIsDisabled('itens_buyin')
            ->assertFormFieldIsDisabled('estrutura_blinds_id')
            ->assertFormFieldIsDisabled('mesas')
            ->assertActionHidden('agendar')
            ->assertActionHidden('cancelar')
            ->fillForm(['observacoes' => 'Noite de referência'])
            ->call('save')
            ->assertHasNoFormErrors();

        $noite->refresh();
        $this->assertSame('Noite de referência', $noite->observacoes);
        $this->assertSame($itens, $noite->itens()->count());
        $this->assertSame(2, $noite->mesas()->count());
    }

    public function test_so_exclui_rascunho_sem_entradas(): void
    {
        $this->seed(PokerNight18Seeder::class);
        $noite = Jogo::query()->where('nome', PokerNight18Seeder::NOME)->sole();
        $rascunho = Jogo::factory()->create();

        Livewire::test(ListJogos::class)
            ->assertActionHidden(TestAction::make('delete')->table($noite))
            ->callAction(TestAction::make('delete')->table($rascunho));

        $this->assertModelMissing($rascunho);
    }

    public function test_grupo_geral_so_ve(): void
    {
        $this->entrarNoPainel('Geral');
        $jogo = Jogo::factory()->create();

        Livewire::test(ListJogos::class)
            ->assertCanSeeTableRecords([$jogo])
            ->assertActionHidden(TestAction::make('edit')->table($jogo));

        $this->get(JogoResource::getUrl('create'))->assertForbidden();
    }

    public function test_simulacao_do_prize_pool_com_o_formulario_nao_salvo(): void
    {
        // 12 × 100 + 6 rebuys × 100 + 4 add-ons × 50 = 2.000; taxas 190 + 2 × 215 = 620; prize pool 1.380.
        // 12 jogadores pela regra do clube: 50 / 30 / 20 → 690 / 414 / 276.
        Livewire::test(CreateJogo::class)
            ->fillForm($this->torneio(['simular_jogadores' => 12, 'simular_rebuys' => 6, 'simular_add_ons' => 4]))
            ->assertSee(['Simulação do prize pool', 'R$ 2.000', '−R$ 190', '−R$ 430', 'R$ 1.380', 'R$ 690', 'R$ 414', 'R$ 276'])
            ->fillForm(['metodo_premiacao' => MetodoPremiacao::Manual->value, 'premiacoes' => [['percentual' => '70'], ['percentual' => '30']]])
            ->assertSee(['R$ 966', 'R$ 414']);

        $this->assertSame(0, Jogo::query()->count());
    }

    public function test_torneio_escolhe_a_estrutura_de_blinds(): void
    {
        $this->seed(EstruturasBlindsSeeder::class);
        $turbo = EstruturaBlinds::query()->where('nome', EstruturasBlindsSeeder::TURBO)->sole();
        $inativa = EstruturaBlinds::query()->create(['nome' => 'Antiga', 'ativo' => false]);

        Livewire::test(CreateJogo::class)
            ->fillForm($this->torneio(['estrutura_blinds_id' => (string) $turbo->id]))
            ->assertFormFieldExists('estrutura_blinds_id', fn ($campo): bool => ! array_key_exists($inativa->id, $campo->getOptions()))
            ->assertSee(['Níveis de 15 min. Intervalo depois do nível 6', '100 / 200', '1:15', '4h15'])
            ->call('create')
            ->assertHasNoFormErrors();

        $jogo = Jogo::query()->sole();
        $this->assertTrue($jogo->estruturaBlinds->is($turbo));

        // Virou cash game: sem estrutura.
        Livewire::test(EditJogo::class, ['record' => $jogo->getRouteKey()])
            ->fillForm(['modalidade' => Modalidade::CashGame->value, 'valor_ficha' => '0.01'])
            ->assertFormFieldIsHidden('estrutura_blinds_id')
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull($jogo->fresh()->estrutura_blinds_id);
    }

    public function test_jogo_novo_vem_com_os_padroes_das_configuracoes(): void
    {
        PadroesDoJogo::gravar([
            ...PadroesDoJogo::INICIAIS,
            'horario' => '19:30', 'lugares_por_mesa' => 8, 'tamanho_mesa_final' => 8,
            'buyin_valor' => '120', 'buyin_fichas' => 25000, 'taxa_nome' => 'Casa', 'taxa_percentual' => '10',
        ]);

        $form = Livewire::test(CreateJogo::class)->get('data');

        $this->assertSame(['19:30', 8, 8], [$form['horario'], $form['lugares_por_mesa'], $form['tamanho_mesa_final']]);
        $this->assertSame(['120', 25000], [array_values($form['itens_buyin'])[0]['valor'], array_values($form['itens_buyin'])[0]['fichas']]);
        $this->assertSame([['Casa', 'percentual', '10']], array_map(fn (array $t) => [$t['nome'], $t['calculo'], $t['valor']], array_values($form['taxas'])));
    }
}
