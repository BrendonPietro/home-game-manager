<?php

namespace Tests\Feature\Site;

use App\Livewire\Jogo\AoVivo;
use App\Models\EstruturaBlinds;
use App\Models\Jogo;
use App\Services\Jogo\Relogio;
use Database\Seeders\PokerNight18Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\Concerns\MontaNoite;
use Tests\TestCase;

/** v2: relógio na faixa ao vivo, os controles (relogio.controlar) e a sugestão de encerrar rebuys no intervalo. */
class RelogioAoVivoTest extends TestCase
{
    use MontaNoite, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->travelTo(Carbon::parse('2026-10-31 20:00:00'));
        $this->montarNoite();
        $this->actingAs($this->admin, 'web');
    }

    private function noite18(): Jogo
    {
        $this->seed(PokerNight18Seeder::class);

        return Jogo::query()->where('nome', PokerNight18Seeder::NOME)->sole();
    }

    /** Índice, segundos e se corre, do pacote que a tela manda para o motor do relógio no navegador. */
    private function pacote($tela): array
    {
        $pacote = $tela->viewData('relogio')->paraNavegador();

        return [$pacote['indice'], $pacote['restante'], $pacote['rodando']];
    }

    /** Estrutura curta no jogo do MontaNoite (fase de rebuys): N1 e N2 de 10 min, Intervalo de 5, N3. */
    private function comEstruturaCurta(): void
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

    public function test_relogio_na_faixa_e_os_controles(): void
    {
        $noite = $this->noite18();

        // Seed: parado no nível 6 com 14:32, como a faixa do protótipo.
        $tela = Livewire::test(AoVivo::class, ['jogo' => $noite])
            ->assertSee(['Nível 6', '100 / 200', 'Relógio · pausado', '14:32'])
            ->assertSeeHtml('x-data="relogioTorneio"')
            ->assertSeeHtml('rh-aovivo__rodape com-relogio')
            ->assertSeeHtml("wire:click=\"controlarRelogio('continuar')\"")
            ->tap(fn ($t) => $this->assertSame([5, 872.0, false], $this->pacote($t)))
            ->call('controlarRelogio', 'continuar')
            ->tap(fn ($t) => $this->assertTrue($t->viewData('relogio')->rodando))
            ->assertSeeHtml("wire:click=\"controlarRelogio('pausar')\"");

        $this->assertNotNull($noite->fresh()->relogio_desde);

        // 14:40 depois o nível 6 acabou: intervalo com 14:52; a faixa mostra o que vem depois.
        $this->travel(880)->seconds();
        $tela->call('$refresh')
            ->assertSee(['A seguir · nível 7', '150 / 300', 'Intervalo', '14:52'])
            ->assertDontSee('hora de encerrar os rebuys'); // a noite já está no intervalo do torneio

        $tela->call('abrirRelogio')
            ->assertSet('modal', 'relogio')
            ->assertSee(['Correndo', 'Depois: nível 7 · 150 / 300 · 15 min', 'ANTERIOR', 'PAUSAR', 'PRÓXIMO', '−5 min', '−1 min', '+1 min', '+5 min'])
            ->call('controlarRelogio', 'ajustar', 1)
            ->assertSee('15:52')
            ->call('controlarRelogio', 'proximo')
            ->assertSee(['Nível 7 · 150 / 300', '15:00', 'Depois: nível 8 · 200 / 400 · 15 min'])
            ->call('controlarRelogio', 'pausar')
            ->assertSee(['Pausado', 'CONTINUAR'])
            ->call('controlarRelogio', 'pausar')
            ->assertSet('modal', 'relogio')
            ->assertSee('O relógio já está pausado.')
            ->call('controlarRelogio', 'qualquer'); // comando que não existe: nada acontece

        $noite->refresh();
        $this->assertSame([7, '150 / 300', 900, null], [$noite->nivel_atual, $noite->blinds_atual, $noite->relogio_segundos, $noite->relogio_desde]);
    }

    public function test_sugere_encerrar_rebuys_no_intervalo(): void
    {
        $this->comEstruturaCurta();

        $tela = Livewire::test(AoVivo::class, ['jogo' => $this->jogo])
            ->assertSee(['Não iniciado', '10:00'])
            ->assertSeeHtml("wire:click=\"controlarRelogio('continuar')\"")
            ->call('controlarRelogio', 'continuar')
            ->assertDontSee('hora de encerrar os rebuys');

        // 20 min: o relógio chegou ao intervalo com os rebuys abertos.
        $this->travel(20)->minutes();
        $tela->call('$refresh')
            ->assertSee(['Intervalo', '05:00', 'A seguir · nível 3', '300 / 600'])
            ->assertSee('hora de encerrar os rebuys e abrir o add-on')
            ->call('abrirFase')
            ->assertSet('modal', 'encerrar');

        // Operador vê o relógio, mas não a sugestão nem os controles.
        $this->actingAs($this->operador, 'web');
        Livewire::test(AoVivo::class, ['jogo' => $this->jogo->fresh()])
            ->assertSee(['Intervalo', '05:00'])
            ->assertDontSee('hora de encerrar os rebuys')
            ->assertDontSeeHtml('controlarRelogio')
            ->assertDontSeeHtml('wire:click="abrirRelogio"')
            ->call('controlarRelogio', 'pausar')
            ->assertSee('Só quem controla o relógio mexe nele.');

        $this->assertNotNull($this->jogo->fresh()->relogio_desde);

        // Passou o intervalo: a sugestão some (o botão da fase continua na lateral).
        $this->actingAs($this->admin, 'web');
        $this->travel(5)->minutes();
        $tela->call('$refresh')
            ->assertSee(['Nível 3', '300 / 600', '10:00'])
            ->assertDontSee('hora de encerrar os rebuys')
            ->assertSee('ENCERRAR REBUYS');
    }

    public function test_jogo_sem_estrutura_nao_tem_relogio(): void
    {
        Livewire::test(AoVivo::class, ['jogo' => $this->jogo])
            ->assertDontSeeHtml('x-data="relogioTorneio"')
            ->assertDontSee('Relógio')
            ->assertDontSeeHtml('com-relogio')
            ->assertSeeHtml('wire:click="abrirNivel"');
    }
}
