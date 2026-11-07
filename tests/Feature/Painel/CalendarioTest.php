<?php

namespace Tests\Feature\Painel;

use App\Actions\Agenda\SalvarEvento;
use App\Enums\StatusAluguel;
use App\Enums\TipoEvento;
use App\Filament\Pages\Calendario;
use App\Filament\Schemas\EventoForm;
use App\Models\Evento;
use App\Models\Jogo;
use App\Models\Mesa;
use App\Services\Agenda\Conflitos;
use App\Services\Jogo\EventoDoJogo;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\UsaPainel;
use Tests\TestCase;

class CalendarioTest extends TestCase
{
    use RefreshDatabase, UsaPainel;

    private Mesa $mesa1;

    private Mesa $mesa2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entrarNoPainel('Admin');
        $this->mesa1 = Mesa::factory()->create(['nome' => 'Mesa 01', 'valor_aluguel' => 300]);
        $this->mesa2 = Mesa::factory()->create(['nome' => 'Mesa 02', 'valor_aluguel' => 250]);
    }

    private function jogo(string $nome, string $data, string $horario = '20:00'): Jogo
    {
        $jogo = Jogo::factory()->agendado()->comEntradasPadrao()->create(['nome' => $nome, 'data' => $data, 'horario' => $horario]);
        $jogo->mesas()->create(['nome' => 'Mesa 1', 'lugares' => 9, 'ordem' => 1]);
        app(EventoDoJogo::class)->sincronizar($jogo);

        return $jogo;
    }

    private function evento(array $dados): Evento
    {
        return app(SalvarEvento::class)(null, $dados + ['data' => '2026-10-08', 'mesas' => [$this->mesa2->id]]);
    }

    private function aluguel(string $cliente, string $status = 'reservado', array $dados = []): Evento
    {
        return $this->evento($dados + ['tipo' => 'aluguel', 'cliente' => $cliente, 'valor' => '300', 'status' => $status, 'hora_inicio' => '19:00', 'hora_fim' => '23:30']);
    }

    private function calendario(string $mes = '2026-10')
    {
        return Livewire::withQueryParams(['mes' => $mes])->test(Calendario::class);
    }

    public function test_grade_do_mes_mostra_jogos_alugueis_reservas_e_bloqueios(): void
    {
        $this->jogo('Poker Night #18', '2026-10-04');
        $this->aluguel('Felipe');
        $this->aluguel('Igor', 'cancelado', ['data' => '2026-10-12']);
        $this->evento(['tipo' => 'reserva', 'nome' => 'Reserva — Diego', 'data' => '2026-10-10', 'hora_inicio' => '18:00']);
        $this->evento(['tipo' => 'bloqueio', 'nome' => 'Manutenção Mesa 03', 'data' => '2026-10-15']);
        $this->aluguel('Renan', dados: ['data' => '2026-09-28']);

        $this->get(Calendario::getUrl(['mes' => '2026-10']))->assertOk();

        $this->calendario()
            ->assertSee('Outubro 2026')
            ->assertSee('♠ Poker Night #18')
            ->assertSee('19:00 · Felipe')
            ->assertSee('Reserva — Diego')
            ->assertSee('Manutenção Mesa 03')
            ->assertSee('dia todo')
            ->assertDontSee('Igor') // aluguel cancelado sai da grade
            ->assertSee('Renan') // 28/09 aparece na primeira semana da grade de outubro
            ->call('mudarMes', 1)
            ->assertSet('mes', '2026-11')
            ->assertSee('Novembro 2026')
            ->assertDontSee('Poker Night #18');
    }

    public function test_cria_aluguel_clicando_no_dia(): void
    {
        $this->calendario()
            ->mountAction(TestAction::make('novoEvento')->arguments(['data' => '2026-10-08']))
            ->assertSchemaStateSet(['data' => '2026-10-08', 'tipo' => 'aluguel'])
            ->fillForm(['cliente' => 'Felipe', 'hora_inicio' => '19:00', 'hora_fim' => '23:30', 'mesas' => [(string) $this->mesa2->id]])
            ->assertSchemaStateSet(['valor' => '250.00']) // sugere o aluguel da mesa
            ->fillForm(['valor' => '300', 'status' => 'confirmado'])
            ->callMountedAction()
            ->assertHasNoFormErrors();

        $evento = Evento::query()->sole();
        $this->assertSame([TipoEvento::Aluguel, 'Mesa alugada — Felipe', '2026-10-08'], [$evento->tipo, $evento->nome, $evento->data->toDateString()]);
        $this->assertSame(['Mesa 02'], $evento->mesas->pluck('nome')->all());
        $this->assertSame(['Felipe', '300.00', StatusAluguel::Confirmado], [$evento->aluguel->cliente, $evento->aluguel->valor, $evento->aluguel->status]);
    }

    public function test_avisa_mesa_ocupada_no_horario(): void
    {
        $this->aluguel('Felipe'); // Mesa 02, 08/10 das 19:00 às 23:30

        $this->calendario()
            ->mountAction(TestAction::make('novoEvento')->arguments(['data' => '2026-10-08']))
            ->fillForm(['tipo' => 'reserva', 'hora_inicio' => '18:00', 'hora_fim' => '22:00', 'mesas' => [(string) $this->mesa1->id]])
            ->assertMountedActionModalSee('Mesa 01 está livre nesse horário.')
            ->fillForm(['mesas' => [(string) $this->mesa1->id, (string) $this->mesa2->id]])
            ->assertMountedActionModalSee('Mesa 02 já tem Mesa alugada — Felipe (19:00–23:30) nesse horário.');
    }

    public function test_torneio_nao_ocupa_as_mesas_do_cadastro(): void
    {
        $this->jogo('Poker Night #19', '2026-10-11');

        $this->calendario()
            ->mountAction(TestAction::make('novoEvento')->arguments(['data' => '2026-10-11']))
            ->fillForm(['tipo' => 'aluguel', 'cliente' => 'Renan', 'hora_inicio' => '19:00', 'hora_fim' => '23:00', 'mesas' => [(string) $this->mesa1->id]])
            ->assertMountedActionModalSee('Mesa 01 está livre nesse horário.')
            ->assertMountedActionModalDontSee('Poker Night #19');
    }

    public function test_edita_e_exclui_evento(): void
    {
        $evento = $this->evento(['tipo' => 'reserva', 'nome' => 'Reserva — Diego', 'hora_inicio' => '18:00']);

        $this->calendario()
            ->callAction(TestAction::make('editarEvento')->arguments(['evento' => $evento->id]), data: ['nome' => 'Reserva — aniversário do Diego', 'hora_fim' => '23:00'])
            ->assertHasNoFormErrors();

        $this->assertSame(['Reserva — aniversário do Diego', '18:00–23:00'], [$evento->fresh()->nome, $evento->fresh()->horario()]);

        $this->calendario()
            ->callAction([
                TestAction::make('editarEvento')->arguments(['evento' => $evento->id]),
                TestAction::make('excluirEvento'),
            ]);

        $this->assertSame(0, Evento::query()->count());
    }

    public function test_evento_do_jogo_so_abre_o_resumo(): void
    {
        $jogo = $this->jogo('Poker Night #18', '2026-10-04');

        $this->calendario()
            ->assertActionHidden(TestAction::make('editarEvento')->arguments(['evento' => $jogo->evento->id]))
            ->mountAction(TestAction::make('verEvento')->arguments(['evento' => $jogo->evento->id]))
            ->assertMountedActionModalSee('♠ Torneio')
            ->assertMountedActionModalSee('Dom 04/10/2026 · 20:00 · Mesa 1') // as mesas do próprio jogo
            ->assertMountedActionModalSee('R$ 100')
            ->assertMountedActionModalSee('Abrir no site');
    }

    public function test_geral_ve_o_calendario_mas_nao_cria(): void
    {
        $this->entrarNoPainel('Geral');
        $evento = $this->aluguel('Felipe');

        $this->get(Calendario::getUrl())->assertOk();

        $this->calendario()
            ->assertActionHidden('novoEvento')
            ->assertActionHidden(TestAction::make('editarEvento')->arguments(['evento' => $evento->id]))
            ->mountAction(TestAction::make('verEvento')->arguments(['evento' => $evento->id]))
            ->assertMountedActionModalSee(['Cliente', 'Felipe', 'R$ 300', 'Reservado']);
    }

    public function test_conflitos_de_mesa(): void
    {
        $this->aluguel('Felipe'); // Mesa 02, 08/10, 19:00–23:30
        $this->evento(['tipo' => 'bloqueio', 'nome' => 'Manutenção', 'data' => '2026-10-15', 'mesas' => [$this->mesa1->id]]);
        $this->aluguel('Igor', 'cancelado', ['data' => '2026-10-09']);
        $this->aluguel('Renan', dados: ['data' => '2026-10-10', 'hora_inicio' => '22:00', 'hora_fim' => '02:00']);

        $conflitos = app(Conflitos::class);
        $nomes = fn (string $data, ?string $de, ?string $ate, array $mesas): array => $conflitos->de($data, $de, $ate, $mesas)->pluck('nome')->all();

        $this->assertSame(['Mesa alugada — Felipe'], $nomes('2026-10-08', '23:00', null, [$this->mesa2->id]));
        $this->assertSame([], $nomes('2026-10-08', '23:30', '23:59', [$this->mesa2->id])); // começa quando o outro acaba
        $this->assertSame([], $nomes('2026-10-08', '19:00', '23:00', [$this->mesa1->id])); // outra mesa
        $this->assertSame(['Manutenção'], $nomes('2026-10-15', '14:00', '16:00', [$this->mesa1->id])); // bloqueio do dia todo
        $this->assertSame([], $nomes('2026-10-09', '19:00', '23:00', [$this->mesa2->id])); // aluguel cancelado
        $this->assertSame(['Mesa alugada — Renan'], $nomes('2026-10-10', '23:30', '00:30', [$this->mesa2->id])); // os dois passam da meia-noite
        $this->assertSame(['Mesa alugada — Renan'], $nomes('2026-10-10', '20:00', null, [$this->mesa2->id])); // sem fim: até o fim do dia
    }

    public function test_aluguel_que_vira_reserva_perde_o_aluguel_e_pago_guarda_a_data(): void
    {
        $evento = $this->aluguel('Felipe', 'pago');
        $pagoEm = $evento->aluguel->pago_em;
        $this->travel(2)->days();

        app(SalvarEvento::class)($evento, ['valor' => '350'] + EventoForm::preencher($evento));
        $this->assertTrue($pagoEm->equalTo($evento->fresh()->aluguel->pago_em));

        app(SalvarEvento::class)($evento, ['tipo' => 'reserva', 'nome' => 'Reserva — Felipe'] + EventoForm::preencher($evento));
        $this->assertNull($evento->fresh()->aluguel);
        $this->assertSame(TipoEvento::Reserva, $evento->fresh()->tipo);
    }
}
