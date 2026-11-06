<?php

namespace Tests\Feature\Site;

use App\Actions\Jogo\ControlarRelogio;
use App\Actions\Jogo\FecharEntradas;
use App\Enums\ComandoRelogio;
use App\Enums\StatusJogo;
use App\Livewire\Jogo\AoVivo;
use App\Livewire\Jogo\Tv;
use App\Models\EstruturaNivel;
use App\Models\Jogo;
use App\Models\Usuario;
use Database\Seeders\PokerNight18Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\MontaNoite;
use Tests\TestCase;

/** v2: tela cheia para a TV (rota jogos.tv, Livewire Jogo\Tv). */
class TvTest extends TestCase
{
    use MontaNoite, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->montarNoite();
        $this->actingAs($this->admin, 'web');
    }

    private function noite18(): Jogo
    {
        $this->seed(PokerNight18Seeder::class);

        return Jogo::query()->where('nome', PokerNight18Seeder::NOME)->sole();
    }

    public function test_tv_da_noite_18(): void
    {
        $noite = $this->noite18();
        $fichas = (int) $noite->entradas()->validas()->sum('fichas');

        $this->get(route('jogos.tv', $noite))->assertOk()->assertSee('TV · Poker Night #18', false);

        // Relógio parado no nível 6 com 14:32; premiação pela conta de agora (entradas abertas).
        Livewire::test(Tv::class, ['jogo' => $noite])
            ->assertSeeHtml('wire:poll.5s.keep-alive')
            ->assertSeeHtml('x-data="relogioTorneio"')
            ->assertSee(['Poker Night #18', 'Intervalo (add-on)', 'Nível 6', '14:32', 'Pausado', '100 / 200', 'Depois: Intervalo · 15 min'])
            ->assertSee(['Prize pool', 'R$ 1.890', 'Premiação até agora', 'R$ 950', 'R$ 565', 'R$ 375'])
            ->assertSee(['Jogando', 'Entradas', '22', 'Stack médio', EstruturaNivel::fichas((int) round($fichas / 12)), 'Intervalo em'])
            ->assertSee(['SOM DESLIGADO', 'TELA CHEIA', 'VOLTAR']);
    }

    public function test_intervalo_mostra_a_volta_e_entradas_fechadas_a_premiacao_gravada(): void
    {
        $noite = $this->noite18();
        app(ControlarRelogio::class)($noite, ComandoRelogio::Proximo, $this->admin);

        $tela = Livewire::test(Tv::class, ['jogo' => $noite->fresh()])
            ->assertSee(['Intervalo', '15:00', 'Na volta · nível 7', '150 / 300'])
            // No intervalo, o "Intervalo em" fica escondido (o motor mostra de novo na volta).
            ->assertSeeHtml('x-show="intervaloEm !== null"  style="display:none"');

        app(FecharEntradas::class)($noite->fresh(), $this->admin);
        $tela->call('$refresh')
            ->assertSee(['Entradas fechadas', 'Premiação', 'R$ 950'])
            ->assertDontSee('Premiação até agora');
    }

    public function test_qualquer_conta_do_site_ve_a_tv(): void
    {
        $this->actingAs($this->operador, 'web');
        $this->get(route('jogos.tv', $this->jogo))->assertOk();

        // A tela ao vivo abre a TV numa aba nova.
        Livewire::test(AoVivo::class, ['jogo' => $this->jogo])
            ->assertSeeHtml('href="'.route('jogos.tv', $this->jogo).'" target="_blank"');

        auth('web')->logout();
        $this->get(route('jogos.tv', $this->jogo))->assertRedirect(route('login'));
    }

    public function test_sem_estrutura_mostra_o_nivel_digitado(): void
    {
        $this->jogo->forceFill(['nivel_atual' => 3, 'blinds_atual' => '50 / 100'])->save();

        Livewire::test(Tv::class, ['jogo' => $this->jogo])
            ->assertSee(['Nível 3', '50 / 100', 'Rebuys abertos'])
            ->assertSeeHtml('sem-relogio')
            ->assertDontSeeHtml('x-data="relogioTorneio"')
            ->assertDontSee('Intervalo em');
    }

    public function test_antes_de_comecar_e_depois_de_finalizar(): void
    {
        $agendado = Jogo::factory()->agendado()->comEntradasPadrao()->create(['nome' => 'Poker Night #19']);
        $agendado->participantes()->create(['usuario_id' => Usuario::factory()->create()->id]); // confirmado

        Livewire::test(Tv::class, ['jogo' => $agendado])
            ->assertSee(['Poker Night #19', '1 confirmado · o relógio começa quando o jogo for iniciado'])
            ->assertDontSeeHtml('x-data="relogioTorneio"');

        // Finalizado: quem ficou com cada prêmio.
        $noite = $this->noite18();
        app(FecharEntradas::class)($noite, $this->admin);
        foreach ($noite->participantes()->with('usuario')->take(3)->get() as $i => $participante) {
            $participante->forceFill(['posicao_final' => $i + 1])->save();
            $nomes[] = $participante->nome();
        }
        $noite->forceFill(['status' => StatusJogo::Finalizado])->save();

        Livewire::test(Tv::class, ['jogo' => $noite->fresh()])
            ->assertSee(['Fim de jogo', 'Poker Night #18', '1º', $nomes[0], 'R$ 950', '2º', $nomes[1], 'R$ 565', '3º', $nomes[2], 'R$ 375'])
            ->assertDontSeeHtml('x-data="relogioTorneio"');
    }

    public function test_jogo_fora_do_site_volta_para_a_lista(): void
    {
        $rascunho = Jogo::factory()->create(['nome' => 'Rascunho']);

        $this->get(route('jogos.tv', $rascunho))
            ->assertRedirect(route('jogos.index'))
            ->assertSessionHas('aviso', 'Rascunho não está disponível no site.');
    }
}
