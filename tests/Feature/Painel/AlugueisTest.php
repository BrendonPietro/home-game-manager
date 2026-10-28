<?php

namespace Tests\Feature\Painel;

use App\Actions\Agenda\SalvarEvento;
use App\Enums\StatusAluguel;
use App\Filament\Resources\Alugueis\AluguelResource;
use App\Filament\Resources\Alugueis\Pages\ListAlugueis;
use App\Filament\Resources\Alugueis\Widgets\TotaisDosAlugueis;
use App\Models\Aluguel;
use App\Models\Evento;
use App\Models\Mesa;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\UsaPainel;
use Tests\TestCase;

class AlugueisTest extends TestCase
{
    use RefreshDatabase, UsaPainel;

    private Mesa $mesa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entrarNoPainel('Admin');
        $this->mesa = Mesa::factory()->create(['nome' => 'Mesa 01', 'valor_aluguel' => 300]);
    }

    private function aluguel(string $cliente, string $status, string $valor, string $data): Aluguel
    {
        return app(SalvarEvento::class)(null, [
            'tipo' => 'aluguel', 'cliente' => $cliente, 'status' => $status, 'valor' => $valor, 'data' => $data,
            'hora_inicio' => '19:00', 'hora_fim' => '23:30', 'mesas' => [$this->mesa->id],
        ])->aluguel;
    }

    public function test_lista_com_totais_por_status_e_abas(): void
    {
        $felipe = $this->aluguel('Felipe', 'confirmado', '300', '2026-10-08');
        $gustavo = $this->aluguel('Gustavo', 'reservado', '300', '2026-10-22');
        $clube = $this->aluguel('Clube X', 'reservado', '550', '2026-10-24');
        $diego = $this->aluguel('Diego', 'pago', '300', '2026-09-26');
        $igor = $this->aluguel('Igor', 'cancelado', '300', '2026-09-12');

        $this->get(AluguelResource::getUrl('index'))->assertOk();

        Livewire::test(ListAlugueis::class)
            ->assertCanSeeTableRecords([$clube, $gustavo, $felipe, $diego, $igor], inOrder: true) // data mais recente primeiro
            ->assertSee(['Mesa 01', '19:00–23:30', 'R$ 550'])
            ->set('activeTab', 'reservado')
            ->assertCanSeeTableRecords([$gustavo, $clube])
            ->assertCanNotSeeTableRecords([$felipe, $diego, $igor])
            ->set('activeTab', 'todos')
            ->filterTable('periodo', ['de' => '2026-10-01', 'ate' => '2026-10-31'])
            ->assertCanSeeTableRecords([$felipe, $gustavo, $clube])
            ->assertCanNotSeeTableRecords([$diego, $igor]);

        Livewire::test(TotaisDosAlugueis::class)
            ->assertSeeInOrder(['Reservado', 'R$ 850', '2 aluguéis', 'Confirmado', 'R$ 300', '1 aluguel', 'Pago', 'R$ 300', 'Cancelado', 'R$ 300']);

        // Com o período de outubro, pago e cancelado (setembro) zeram.
        Livewire::test(TotaisDosAlugueis::class, ['tableFilters' => ['periodo' => ['de' => '2026-10-01', 'ate' => '2026-10-31']]])
            ->assertSeeInOrder(['Pago', 'R$ 0', '0 aluguéis', 'Cancelado', 'R$ 0']);
    }

    public function test_marcar_pago(): void
    {
        $aberto = $this->aluguel('Felipe', 'confirmado', '300', '2026-10-08');
        $pago = $this->aluguel('Diego', 'pago', '300', '2026-09-26');

        Livewire::test(ListAlugueis::class)
            ->assertActionHidden(TestAction::make('marcarPago')->table($pago))
            ->callAction(TestAction::make('marcarPago')->table($aberto));

        $this->assertSame(StatusAluguel::Pago, $aberto->fresh()->status);
        $this->assertNotNull($aberto->fresh()->pago_em);
    }

    public function test_novo_editar_e_excluir(): void
    {
        Livewire::test(ListAlugueis::class)
            ->mountAction('novoAluguel')
            ->assertMountedActionModalDontSee('Tipo de evento')
            ->fillForm(['cliente' => 'Renan', 'data' => '2026-10-31', 'hora_inicio' => '15:00', 'hora_fim' => '20:00', 'mesas' => [(string) $this->mesa->id]])
            ->assertSchemaStateSet(['valor' => '300.00'])
            ->callMountedAction()
            ->assertHasNoFormErrors();

        $aluguel = Aluguel::query()->sole();
        $this->assertSame(['Renan', StatusAluguel::Reservado, 'Mesa alugada — Renan'], [$aluguel->cliente, $aluguel->status, $aluguel->evento->nome]);

        // Outro aluguel na mesma mesa e horário avisa; o próprio não conta como conflito.
        $this->aluguel('Gustavo', 'reservado', '300', '2026-10-31');

        Livewire::test(ListAlugueis::class)
            ->mountAction(TestAction::make('editar')->table($aluguel))
            ->assertMountedActionModalSee('Mesa 01 já tem Mesa alugada — Gustavo (19:00–23:30) nesse horário.')
            ->assertMountedActionModalDontSee('Mesa alugada — Renan')
            ->fillForm(['valor' => '250', 'status' => 'confirmado'])
            ->callMountedAction()
            ->assertHasNoFormErrors();

        $this->assertSame(['250.00', StatusAluguel::Confirmado], [$aluguel->fresh()->valor, $aluguel->fresh()->status]);

        Livewire::test(ListAlugueis::class)
            ->callAction(TestAction::make('delete')->table($aluguel));

        // Sai do calendário também.
        $this->assertSame(['Mesa alugada — Gustavo'], Evento::query()->pluck('nome')->all());
        $this->assertSame(['Gustavo'], Aluguel::query()->pluck('cliente')->all());
    }

    public function test_geral_nao_ve_alugueis(): void
    {
        $this->entrarNoPainel('Geral');

        $this->get(AluguelResource::getUrl('index'))->assertForbidden();
    }
}
