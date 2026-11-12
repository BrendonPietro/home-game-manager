<?php

namespace Tests\Feature\Painel;

use App\Actions\Jogo\ExcluirJogo;
use App\Enums\CalculoTaxa;
use App\Enums\MetodoPremiacao;
use App\Enums\Modalidade;
use App\Enums\StatusJogo;
use App\Enums\TipoAlteracao;
use App\Enums\TipoItem;
use App\Filament\Resources\Jogos\JogoResource;
use App\Filament\Resources\Jogos\Pages\CreateJogo;
use App\Filament\Resources\Jogos\Pages\EditJogo;
use App\Filament\Resources\Jogos\Pages\ListJogos;
use App\Filament\Resources\Jogos\Pages\ViewJogo;
use App\Models\Entrada;
use App\Models\EstruturaBlinds;
use App\Models\Evento;
use App\Models\Jogo;
use App\Models\Mesa;
use App\Models\Participante;
use App\Models\Usuario;
use App\Services\Jogo\Calculadora;
use App\Support\PadroesDoJogo;
use App\Support\SugestaoDePremiacao;
use Database\Seeders\EstruturasBlindsSeeder;
use Database\Seeders\PokerNight18Seeder;
use Database\Seeders\RegrasPremiacaoSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\Repeater;
use Illuminate\Auth\Access\AuthorizationException;
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
                ['padrao' => false, 'nome' => 'Rebuy duplo', 'valor' => '200', 'fichas' => 40000, 'premiacao' => 'outro', 'premiacao_outro' => '75', 'limite_por_jogador' => null, 'ativo' => true],
            ],
            'itens_addon' => [['padrao' => true, 'nome' => 'Add-on', 'valor' => '50', 'fichas' => 20000, 'limite_por_jogador' => 1, 'ativo' => true]],
            'opcionais' => [
                ['nome' => 'Opcional', 'valor' => '10', 'fichas' => 20000, 'compravel_com' => 'entrada', 'premiacao' => '50', 'obrigatorio' => false, 'ativo' => true],
                ['nome' => 'Churrasco', 'valor' => '30', 'fichas' => 20000, 'compravel_com' => 'jogador', 'premiacao' => '0', 'obrigatorio' => false, 'pago_antes' => true, 'ativo' => true],
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
        $mesa = Mesa::factory()->create(['nome' => 'Mesa 01', 'lugares' => 8]); // só do cadastro: o jogo não usa
        $dealer = Usuario::factory()->dealer()->create(['nome' => 'Sérgio']);

        Livewire::test(CreateJogo::class)
            ->fillForm($this->torneio([
                'mesas' => [['nome' => 'Mesa 1', 'dealer_id' => (string) $dealer->id, 'lugares' => 8]],
            ]))
            ->call('create')
            ->assertHasNoFormErrors();

        $jogo = Jogo::query()->sole();
        $this->assertSame(StatusJogo::Rascunho, $jogo->status);

        $entradas = $jogo->itensDeEntrada()->get();
        $this->assertSame(['Buy-in', 'Rebuy', 'Rebuy duplo', 'Add-on'], $entradas->sortBy(fn ($i) => [$i->tipo->value === 'buyin' ? 0 : ($i->tipo->value === 'rebuy' ? 1 : 2), $i->ordem])->pluck('nome')->values()->all());
        $this->assertSame(3, $entradas->where('padrao', true)->count());
        // O rebuy duplo usou "Outro" com 75%; o resto ficou no padrão de 100%.
        $this->assertSame(['Rebuy duplo' => '75.00'], $entradas->reject(fn ($i) => $i->percentual_premiacao === '100.00')->pluck('percentual_premiacao', 'nome')->all());

        $opcional = $jogo->opcionais()->where('nome', 'Opcional')->sole();
        $churrasco = $jogo->opcionais()->where('nome', 'Churrasco')->sole();
        $this->assertSame('50.00', $opcional->percentual_premiacao);
        $this->assertTrue($opcional->compravelCom(TipoItem::Rebuy));
        $this->assertTrue($churrasco->porJogador());
        $this->assertSame('0.00', $churrasco->percentual_premiacao);
        $this->assertSame([false, true], [$opcional->pago_antes, $churrasco->pago_antes]); // churrasco pago antes: entra junto do buy-in

        $this->assertSame([CalculoTaxa::Fixo, CalculoTaxa::PorUnidade], $jogo->taxas->pluck('calculo')->all());
        $this->assertSame(2, $jogo->taxas[1]->quantidade);

        $this->assertSame($dealer->id, $jogo->mesas->sole()->dealer_id);
        $this->assertSame('Mesa 1', $jogo->mesas->sole()->nome);
        $this->assertSame([], $jogo->evento->mesas->pluck('id')->all()); // o jogo não ocupa o cadastro
        $this->assertSame('2026-10-11', $jogo->evento->data->toDateString());
    }

    public function test_premiacao_manual_precisa_somar_100(): void
    {
        // Não fecha 100%: alerta com OK e não salva.
        Livewire::test(CreateJogo::class)
            ->fillForm($this->torneio(['metodo_premiacao' => 'manual', 'premiacoes' => [['percentual' => '60'], ['percentual' => '30']]]))
            ->assertSee('Soma 90%: falta 10%')
            ->call('create')
            ->assertNotified('A premiação não fecha 100%');

        Livewire::test(CreateJogo::class)
            ->fillForm($this->torneio(['metodo_premiacao' => 'manual', 'premiacoes' => [['percentual' => '60'], ['percentual' => '45']]]))
            ->call('create')
            ->assertNotified('A premiação não fecha 100%');

        $this->assertSame(0, Jogo::query()->count());

        // Posição adicionada e não preenchida não existe: some ao salvar, e as outras ficam 1º, 2º, 3º.
        Livewire::test(CreateJogo::class)
            ->fillForm($this->torneio(['metodo_premiacao' => 'manual', 'premiacoes' => [
                ['percentual' => '60'], ['percentual' => null], ['percentual' => '25'], ['percentual' => '15'], ['percentual' => null],
            ]]))
            ->assertSee('Soma 100%')
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(['60.00', '25.00', '15.00'], Jogo::query()->sole()->premiacoes->pluck('percentual')->all());
        $this->assertSame([1, 2, 3], Jogo::query()->sole()->premiacoes->pluck('posicao')->all());
    }

    public function test_sugestoes_de_premiacao_pelo_numero_de_posicoes(): void
    {
        // Em múltiplos de 5, como as entradas e os prêmios.
        $this->assertSame([55, 30, 15], SugestaoDePremiacao::concentrada(3));
        $this->assertSame([40, 35, 25], SugestaoDePremiacao::equilibrada(3));
        $this->assertSame([65, 35], SugestaoDePremiacao::concentrada(2));
        $this->assertSame([100], SugestaoDePremiacao::concentrada(1));

        // Sempre 100%, com pelo menos 1% por posição, também com muitas posições.
        foreach (range(1, 30) as $lugares) {
            foreach ([SugestaoDePremiacao::concentrada($lugares), SugestaoDePremiacao::equilibrada($lugares)] as $divisao) {
                $this->assertSame(100, array_sum($divisao));
                $this->assertCount($lugares, $divisao);
                $this->assertGreaterThanOrEqual(1, min($divisao));
                $this->assertSame($divisao, array_values(collect($divisao)->sortDesc()->all())); // do 1º para o último

                if ($lugares <= 20) {
                    $this->assertSame([], array_values(array_filter($divisao, fn (int $p): bool => $p % 5 !== 0)));
                }
            }
        }

        // No formulário: os botões contam as posições da lista (preenchidas ou não) e o clique preenche.
        Livewire::test(CreateJogo::class)
            ->fillForm($this->torneio(['metodo_premiacao' => 'manual', 'premiacoes' => [['percentual' => null], ['percentual' => null], ['percentual' => null]]]))
            ->assertSee(['Concentrada no 1º · 55 / 30 / 15', 'Mais equilibrada · 40 / 35 / 25'])
            ->callAction(TestAction::make('sugestao_equilibrada')->schemaComponent('sugestoes_da_premiacao'))
            ->assertSee('Soma 100%')
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(['40.00', '35.00', '25.00'], Jogo::query()->sole()->premiacoes->pluck('percentual')->all());
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

    public function test_admin_muda_o_cadastro_do_jogo_em_andamento_e_as_entradas_acompanham(): void
    {
        $this->seed(PokerNight18Seeder::class);
        $noite = Jogo::query()->where('nome', PokerNight18Seeder::NOME)->sole();
        $addons = $noite->entradas()->validas()->where('tipo', TipoItem::Addon)->count();
        $arrecadacao = app(Calculadora::class)->doJogo($noite)->arrecadacao;

        $tela = Livewire::test(EditJogo::class, ['record' => $noite->getRouteKey()])
            ->assertFormFieldIsDisabled('modalidade')
            ->assertFormFieldIsDisabled('estrutura_blinds_id') // o relógio não muda no meio
            ->assertFormFieldIsEnabled('itens_addon')
            ->assertFormFieldIsEnabled('mesas')
            ->assertSee('Jogo em andamento: ao salvar, as entradas já lançadas passam a valer os valores daqui')
            ->assertActionHidden('agendar')
            ->assertActionHidden('cancelar');

        // Add-on de R$ 50 para R$ 60.
        $itens = $tela->get('data.itens_addon');
        $itens[array_key_first($itens)]['valor'] = '60';

        $tela->set('data.itens_addon', $itens)
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified('Jogo refeito pelo cadastro');

        $this->assertSame(['60.00'], $noite->entradas()->validas()->where('tipo', TipoItem::Addon)->pluck('valor')->unique()->values()->all());
        $this->assertSame($arrecadacao + $addons * 1000, app(Calculadora::class)->doJogo($noite)->arrecadacao);

        $alteracao = $noite->alteracoes()->latest('id')->first();
        $this->assertSame(TipoAlteracao::CadastroAlterado, $alteracao->tipo);
        $this->assertStringContainsString("Add-on R$ 50 → R$ 60 ({$addons} entradas)", $alteracao->descricao);
    }

    public function test_sem_a_permissao_o_jogo_em_andamento_fica_travado(): void
    {
        $this->seed(PokerNight18Seeder::class);
        $noite = Jogo::query()->where('nome', PokerNight18Seeder::NOME)->sole();
        $conta = $this->entrarNoPainel('Admin');
        $conta->syncRoles([]);
        $conta->givePermissionTo(['jogos.ver', 'jogos.editar']);

        $this->get(JogoResource::getUrl('edit', ['record' => $noite]))->assertOk();

        Livewire::test(EditJogo::class, ['record' => $noite->getRouteKey()])
            ->assertFormFieldIsDisabled('itens_buyin')
            ->assertFormFieldIsDisabled('mesas')
            ->assertDontSee('Jogo em andamento: ao salvar')
            ->fillForm(['observacoes' => 'Noite de referência'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Noite de referência', $noite->fresh()->observacoes);
        $this->assertSame(0, $noite->alteracoes()->where('tipo', TipoAlteracao::CadastroAlterado)->count());
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

        // Nem pela Action, sem a permissão de excluir com lançamentos.
        $this->expectException(AuthorizationException::class);
        app(ExcluirJogo::class)($noite, $this->entrarNoPainel('Admin'));
    }

    public function test_desenvolvedor_exclui_qualquer_jogo_com_o_que_foi_lancado(): void
    {
        $this->entrarNoPainel('Desenvolvedor');
        $this->seed(PokerNight18Seeder::class);
        $noite = Jogo::query()->where('nome', PokerNight18Seeder::NOME)->sole();
        $outro = Jogo::factory()->create();
        $this->assertSame(StatusJogo::EmAndamento, $noite->status);
        $this->assertTrue(Evento::query()->where('jogo_id', $noite->id)->exists());

        // O modal diz o que vai junto (a Poker Night #18: 12 jogadores, 22 entradas, R$ 2.100).
        $aviso = ExcluirJogo::oQueApaga($noite);
        $this->assertSame('Apaga o jogo e tudo o que foi lançado nele: 12 jogadores e 22 entradas (R$ 2.100). Não dá para desfazer.', $aviso);
        $this->assertSame('O jogo sai do painel e do calendário. Não dá para desfazer.', ExcluirJogo::oQueApaga($outro));

        Livewire::test(ListJogos::class)
            ->mountAction(TestAction::make('delete')->table($noite))
            ->assertMountedActionModalSee($aviso)
            ->callMountedAction();

        // Some com a lista, as entradas e o evento da agenda; o outro jogo fica.
        $this->assertModelMissing($noite);
        $this->assertSame(0, Entrada::query()->where('jogo_id', $noite->id)->count());
        $this->assertSame(0, Participante::query()->where('jogo_id', $noite->id)->count());
        $this->assertFalse(Evento::query()->where('jogo_id', $noite->id)->exists());
        $this->assertModelExists($outro);
    }

    public function test_finalizado_se_exclui_pela_tela_de_visualizar(): void
    {
        $this->entrarNoPainel('Desenvolvedor');
        $jogo = Jogo::factory()->create(['status' => StatusJogo::Finalizado]);

        Livewire::test(ViewJogo::class, ['record' => $jogo->getRouteKey()])
            ->callAction('delete')
            ->assertRedirect(JogoResource::getUrl('index'));

        $this->assertModelMissing($jogo);
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
        // 12 jogadores pela regra do clube: 50 / 30 / 20 → 690 / 414 / 276, arredondados para R$ 5: 695 / 410 / 275.
        Livewire::test(CreateJogo::class)
            ->fillForm($this->torneio(['simular_jogadores' => 12, 'simular_rebuys' => 6, 'simular_add_ons' => 4]))
            ->assertSee(['Simulação do prize pool', 'R$ 2.000', '−R$ 190', '−R$ 430', 'R$ 1.380', 'R$ 695', 'R$ 410', 'R$ 275'])
            ->fillForm(['metodo_premiacao' => MetodoPremiacao::Manual->value, 'premiacoes' => [['percentual' => '70'], ['percentual' => '30']]])
            ->assertSee(['R$ 970', 'R$ 410']); // 966 / 414 arredondados

        $this->assertSame(0, Jogo::query()->count());
    }

    public function test_torneio_com_time_chip_e_bonus_do_pagamento_antecipado(): void
    {
        Livewire::test(CreateJogo::class)
            ->fillForm($this->torneio(['time_chip_fichas' => '10000', 'time_chip_ate_nivel' => '2', 'antecipado_fichas' => '20000']))
            ->assertSee(['Pagamento antecipado', 'Fichas para quem pagou antes'])
            ->call('create')
            ->assertHasNoFormErrors();

        $jogo = Jogo::query()->sole();
        $this->assertSame([10000, 2, 20000], [$jogo->time_chip_fichas, $jogo->time_chip_ate_nivel, $jogo->antecipado_fichas]);
        $this->assertTrue($jogo->temTimeChip());
        $this->assertTrue($jogo->temBonusAntecipado());
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

    public function test_percentual_da_premiacao_volta_no_formulario_e_continua_ao_salvar(): void
    {
        $jogo = Jogo::factory()->comEntradasPadrao()->create();
        $opcional = $jogo->itens()->create(['tipo' => TipoItem::Opcional, 'nome' => 'Opcional', 'valor' => 10, 'percentual_premiacao' => 75, 'compravel_com' => ['buyin']]);
        $churrasco = $jogo->itens()->create(['tipo' => TipoItem::Opcional, 'nome' => 'Churrasco', 'valor' => 30, 'percentual_premiacao' => 0, 'compravel_com' => ['jogador']]);

        // 75% abre como "Outro · 75"; 0% abre no atalho. Salvar sem mexer não muda nada.
        $tela = Livewire::test(EditJogo::class, ['record' => $jogo->getRouteKey()]);
        $linhas = collect($tela->get('data.opcionais'))->keyBy('nome');
        $this->assertSame(['outro', '75'], [$linhas['Opcional']['premiacao'], (string) $linhas['Opcional']['premiacao_outro']]);
        $this->assertSame('0', (string) $linhas['Churrasco']['premiacao']);

        $tela->call('save')->assertHasNoFormErrors();

        $this->assertSame(['75.00', '0.00'], [$opcional->fresh()->percentual_premiacao, $churrasco->fresh()->percentual_premiacao]);
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
