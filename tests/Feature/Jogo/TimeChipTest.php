<?php

namespace Tests\Feature\Jogo;

use App\Actions\Jogo\ControlarRelogio;
use App\Actions\Jogo\RegistrarEntrada;
use App\Enums\ComandoRelogio;
use App\Enums\TipoItem;
use App\Livewire\Jogo\AcoesJogador;
use App\Livewire\Jogo\AoVivo;
use App\Livewire\Jogo\Resumo;
use App\Models\EstruturaBlinds;
use App\Services\Jogo\Entradas;
use App\Services\Jogo\Relogio;
use App\Services\Jogo\TimeChip;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Concerns\MontaNoite;
use Tests\TestCase;

/**
 * Time chip (10.000 fichas para quem entra até o fim do nível 1) e as fichas que cada jogador recebeu:
 * as de cada entrada válida, opcionais incluídos, mais o time chip do buy-in.
 */
class TimeChipTest extends TestCase
{
    use MontaNoite, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-31 20:00:00'));
        $this->montarNoite();
        $this->jogo->update(['time_chip_fichas' => 10000, 'time_chip_ate_nivel' => 1]);
        $this->item(TipoItem::Buyin)->update(['fichas' => 20000]);
        $this->item(TipoItem::Rebuy)->update(['fichas' => 20000]);
    }

    /** Estrutura curta: N1 e N2 de 10 min, intervalo de 5 min, N3 de 10 min. */
    private function comRelogio(): void
    {
        $estrutura = EstruturaBlinds::query()->create(['nome' => 'Curta']);
        foreach ([[100, 200], [200, 400], 'Intervalo', [300, 600]] as $i => $nivel) {
            $estrutura->niveis()->create(is_string($nivel)
                ? ['ordem' => $i + 1, 'e_intervalo' => true, 'nome_intervalo' => $nivel, 'duracao_minutos' => 5]
                : ['ordem' => $i + 1, 'small_blind' => $nivel[0], 'big_blind' => $nivel[1], 'duracao_minutos' => 10]);
        }

        $this->jogo->update(['estrutura_blinds_id' => $estrutura->id]);
        app(Relogio::class)->preparar($this->jogo->fresh());
    }

    public function test_com_relogio_vale_ate_o_fim_do_nivel_do_prazo(): void
    {
        $this->comRelogio();
        $timeChip = app(TimeChip::class);

        // Relógio parado no nível 1 (ainda não começou): vale.
        $this->assertSame(10000, $timeChip->fichasAgora($this->jogo->fresh()));

        app(ControlarRelogio::class)($this->jogo, ComandoRelogio::Continuar, $this->admin);
        $this->travel(9)->minutes();
        $this->assertTrue($timeChip->valeAgora($this->jogo->fresh()));

        // Passou do nível 1: não vale mais.
        $this->travel(2)->minutes();
        $this->assertFalse($timeChip->valeAgora($this->jogo->fresh()));

        // Prazo até o fim do nível 2: vale no 2 e acaba no intervalo depois dele.
        $this->jogo->update(['time_chip_ate_nivel' => 2]);
        $this->assertTrue($timeChip->valeAgora($this->jogo->fresh()));
        $this->travel(10)->minutes(); // 21 min: intervalo
        $this->assertFalse($timeChip->valeAgora($this->jogo->fresh()));
    }

    public function test_sem_estrutura_vale_pelo_nivel_digitado_e_so_no_torneio_com_fichas(): void
    {
        $timeChip = app(TimeChip::class);

        $this->assertTrue($timeChip->valeAgora($this->jogo->fresh())); // sem nível digitado = nível 1
        $this->jogo->update(['nivel_atual' => 2]);
        $this->assertFalse($timeChip->valeAgora($this->jogo->fresh()));

        $this->jogo->update(['nivel_atual' => 1, 'time_chip_fichas' => null]);
        $this->assertFalse($timeChip->valeAgora($this->jogo->fresh()));
        $this->assertNull(TimeChip::texto($this->jogo->fresh()));

        $this->jogo->update(['time_chip_fichas' => 10000]);
        $this->assertSame('Time chip: 10.000 fichas para quem entrar até o fim do nível 1', TimeChip::texto($this->jogo->fresh()));
    }

    public function test_buyin_no_prazo_ganha_o_time_chip_e_as_fichas_se_somam(): void
    {
        $this->comRelogio();
        $ana = $this->jogador('Ana');
        $beto = $this->jogador('Beto');

        $buyinDaAna = $this->entrar($ana, TipoItem::Buyin);
        $this->assertSame([20000, 10000], [$buyinDaAna->fichas, $buyinDaAna->time_chip]);
        $this->assertSame(30000, RegistrarEntrada::fichasDoLancamento($buyinDaAna));

        // Beto chega depois do nível 1: só as fichas do buy-in. O rebuy nunca tem time chip.
        app(ControlarRelogio::class)($this->jogo, ComandoRelogio::Continuar, $this->admin);
        $this->travel(11)->minutes();
        $this->assertSame(0, $this->entrar($beto, TipoItem::Buyin)->time_chip);
        $this->assertSame(0, $this->entrar($ana, TipoItem::Rebuy)->time_chip);

        $porJogador = app(Entradas::class)->porJogador($this->jogo);
        $this->assertSame([50000, 10000], [$porJogador[$ana->id]->fichas, $porJogador[$ana->id]->timeChip]);
        $this->assertSame([20000, 0], [$porJogador[$beto->id]->fichas, $porJogador[$beto->id]->timeChip]);

        // Desfazer o buy-in leva o time chip junto.
        $this->desfazer($beto);
        $this->assertArrayNotHasKey($beto->id, app(Entradas::class)->porJogador($this->jogo)->all());
    }

    public function test_painel_do_jogador_mostra_as_fichas_entregues(): void
    {
        $ana = $this->jogador('Ana');
        $this->actingAs($this->admin, 'web');

        Livewire::test(AcoesJogador::class, ['jogo' => $this->jogo, 'participanteId' => $ana->id])
            ->call('abrirEntrada', 'buyin')
            ->call('confirmarEntrada')
            ->assertSet('aviso', 'Buy-in registrado para Ana: entregar 30.000 fichas (10.000 do time chip).')
            ->assertSee(['Fichas entregues', '30.000', 'buy-in 20.000 · time chip 10.000']);

        app(RegistrarEntrada::class)($ana->fresh(), $this->item(TipoItem::Rebuy), $this->admin, (string) Str::uuid());

        Livewire::test(AcoesJogador::class, ['jogo' => $this->jogo, 'participanteId' => $ana->id])
            ->assertSee(['50.000', 'buy-in 20.000 · time chip 10.000 · rebuys 20.000']);

        // O resumo da noite tem a coluna Fichas.
        Livewire::test(Resumo::class, ['jogo' => $this->jogo])
            ->assertSeeInOrder(['Fichas', 'Total', 'Ana', '50.000']);
    }

    public function test_quem_senta_aqui_diz_as_fichas_do_buyin(): void
    {
        $this->withoutVite();
        $this->actingAs($this->admin, 'web');

        Livewire::test(AoVivo::class, ['jogo' => $this->jogo])
            ->call('clicarAssento', $this->mesa->id, 5)
            ->assertSee('Sentar já lança o buy-in (Buy-in · R$ 100): entregar 30.000 fichas (10.000 do time chip)');

        // Passou do prazo: só as fichas do buy-in.
        $this->jogo->update(['nivel_atual' => 2]);

        Livewire::test(AoVivo::class, ['jogo' => $this->jogo])
            ->call('clicarAssento', $this->mesa->id, 5)
            ->assertSee('Sentar já lança o buy-in (Buy-in · R$ 100): entregar 20.000 fichas')
            ->assertDontSee('do time chip)');
    }
}
