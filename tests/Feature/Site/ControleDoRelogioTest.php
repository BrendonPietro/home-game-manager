<?php

namespace Tests\Feature\Site;

use App\Actions\Jogo\DispararAviso;
use App\Enums\StatusJogo;
use App\Exceptions\RegraDoJogo;
use App\Livewire\Jogo\AoVivo;
use App\Livewire\Jogo\ControleDoRelogio;
use App\Models\Jogo;
use Database\Seeders\PokerNight18Seeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\MontaNoite;
use Tests\TestCase;

/** v3: tela do relógio de quem controla o torneio (/jogos/{jogo}/relogio) e o AVISO. */
class ControleDoRelogioTest extends TestCase
{
    use MontaNoite, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->montarNoite();
    }

    private function noite18(): Jogo
    {
        $this->seed(PokerNight18Seeder::class);

        return Jogo::query()->where('nome', PokerNight18Seeder::NOME)->sole();
    }

    public function test_admin_controla_o_relogio_pela_tela_dele(): void
    {
        $noite = $this->noite18();
        $this->actingAs($this->admin, 'web');

        $this->get(route('jogos.relogio', $noite))->assertOk()->assertSee('Relógio · Poker Night #18', false);

        // Parado no nível 6 com 14:32: o painel grande e os botões.
        $tela = Livewire::test(ControleDoRelogio::class, ['jogo' => $noite])
            ->assertSeeHtml('x-data="relogioTorneio"')
            ->assertSeeHtml('wire:poll.5s.keep-alive')
            ->assertSee(['Nível 6', '14:32', '100 / 200', 'Depois: Intervalo · 15 min', 'Intervalo em'])
            ->assertSee(['ANTERIOR', 'CONTINUAR', 'PRÓXIMO', '−5 min', '+5 min', 'AVISO', 'LINK DA TV'])
            ->call('controlar', 'continuar')
            ->assertSee('PAUSAR');
        $this->assertNotNull($noite->fresh()->relogio_desde);

        $tela->call('controlar', 'proximo')->assertSee(['Intervalo', 'Na volta · nível 7']);

        // AVISO: soma um e o link mostra para o motor tocar.
        $tela->call('avisar')->assertSet('mensagem', 'Aviso enviado: toca nas telas do link em até 2 s.');
        $this->assertSame(1, $noite->fresh()->avisos);

        $aviso = $this->getJson(route('relogio.estado', $noite->relogio_codigo))->json('aviso');
        $this->assertSame(1, $aviso['numero']);
        $this->assertLessThan(5, $aviso['ha']);
    }

    public function test_operador_ve_o_relogio_sem_os_botoes(): void
    {
        $noite = $this->noite18();
        $this->actingAs($this->operador, 'web');

        Livewire::test(ControleDoRelogio::class, ['jogo' => $noite])
            ->assertSee(['Nível 6', '14:32', 'Só quem controla o relógio pausa, troca de nível e dispara o aviso.'])
            ->assertDontSee(['ANTERIOR', 'PRÓXIMO'])
            ->assertDontSeeHtml('wire:click="avisar"')
            // Mesmo chamando direto, a Action barra.
            ->call('avisar')
            ->assertSet('erro', 'Só quem controla o relógio dispara o aviso.');

        $this->assertSame(0, $noite->fresh()->avisos);
    }

    public function test_aviso_so_com_o_jogo_em_andamento(): void
    {
        $this->jogo->forceFill(['status' => StatusJogo::Finalizado])->save();

        $this->assertThrows(fn () => app(DispararAviso::class)($this->jogo, $this->admin), RegraDoJogo::class, 'O aviso só toca com o jogo em andamento.');
        $this->assertThrows(fn () => app(DispararAviso::class)($this->jogo, $this->operador), AuthorizationException::class);

        // A tela não abre fora do andamento: volta para a etapa do jogo.
        $this->actingAs($this->admin, 'web');
        $this->get(route('jogos.relogio', $this->jogo))->assertRedirect();
    }

    public function test_sem_estrutura_fica_o_aviso(): void
    {
        $this->actingAs($this->admin, 'web');
        $this->jogo->forceFill(['nivel_atual' => 3, 'blinds_atual' => '50 / 100'])->save();

        Livewire::test(ControleDoRelogio::class, ['jogo' => $this->jogo])
            ->assertSee(['Nível 3', '50 / 100', 'Sem estrutura de blinds', 'AVISO'])
            ->assertDontSee('PRÓXIMO')
            ->call('avisar')
            ->assertSet('erro', null);

        $this->assertSame(1, $this->jogo->fresh()->avisos);
    }

    public function test_tela_ao_vivo_tem_o_botao_relogio(): void
    {
        $this->actingAs($this->admin, 'web');

        Livewire::test(AoVivo::class, ['jogo' => $this->jogo])
            ->assertSeeHtml('href="'.route('jogos.relogio', $this->jogo).'"')
            ->assertSee('RELÓGIO');
    }

    public function test_aviso_para_o_navegador(): void
    {
        $this->assertSame(['numero' => 0, 'ha' => null], $this->jogo->avisoParaNavegador());

        // O banco guarda a hora em segundos inteiros (até 1 s de diferença; o navegador tolera 30 s).
        $this->travelTo(now()->startOfSecond());
        app(DispararAviso::class)($this->jogo, $this->admin);
        $this->travel(12)->seconds();

        $this->assertSame(['numero' => 1, 'ha' => 12.0], $this->jogo->fresh()->avisoParaNavegador());
    }
}
