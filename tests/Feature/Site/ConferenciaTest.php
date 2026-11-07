<?php

namespace Tests\Feature\Site;

use App\Enums\StatusJogo;
use App\Enums\TipoItem;
use App\Models\Jogo;
use App\Models\Usuario;
use Database\Seeders\PokerNight18Seeder;
use Database\Seeders\RegrasPremiacaoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConferenciaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed([RegrasPremiacaoSeeder::class, PokerNight18Seeder::class]);
        $this->actingAs(Usuario::factory()->foraDaNoite()->create(), 'web');
    }

    /** A noite #18 com o cadastro completo, mas ainda agendada (antes de começar). */
    private function noiteAgendada(): Jogo
    {
        $noite = Jogo::query()->where('nome', PokerNight18Seeder::NOME)->sole();
        $noite->forceFill(['status' => StatusJogo::Agendado, 'fase' => null, 'iniciado_em' => null, 'intervalo_aberto_em' => null])->save();
        $noite->entradas()->delete();

        return $noite;
    }

    public function test_mostra_a_estrutura_cadastrada_no_painel(): void
    {
        $noite = $this->noiteAgendada();

        $this->get(route('jogos.conferencia', $noite))
            ->assertOk()
            ->assertSee('Antes do torneio · 1 de 3')
            ->assertSee('Conferência da estrutura')
            // buy-ins: padrão B, duplo 2, triplo 3
            ->assertSeeInOrder(['Buy-in', 'Entrada padrão · 1 por jogador', '10.000 fichas', 'R$ 100'])
            ->assertSeeInOrder(['>2<', 'Buy-in duplo', 'Conta como 1 entrada', '20.000 fichas', 'R$ 200'], false)
            ->assertSee('sem limite, até fechar as entradas (também no intervalo)')
            ->assertSee('1 por jogador, no intervalo')
            // financeiro e mesas
            ->assertSeeInOrder(['Casa', '10%'])
            ->assertSee('3 posições · 50/30/20')
            ->assertSee('R$ 1.080') // 12 confirmados × R$ 100 − 10%
            ->assertSee('Mesa 01: 9 · Mesa 02: 9')
            ->assertSee('Sérgio · Paulo')
            ->assertSee(route('filament.painel.resources.jogos.edit', $noite));
    }

    public function test_mostra_a_estrutura_de_blinds(): void
    {
        $noite = $this->noiteAgendada();

        $this->get(route('jogos.conferencia', $noite))
            ->assertOk()
            ->assertSeeInOrder(['Estrutura de blinds', 'Turbo 15 min'])
            ->assertSee('Blinds · Turbo 15 min')
            ->assertSee('16 níveis de 15 min · 1 intervalo · 4h15')
            ->assertSeeInOrder(['>6<', '100 / 200', '15 min', '1:15', 'Intervalo', '1:30', '>7<', '150 / 300'], false);

        $noite->update(['estrutura_blinds_id' => null]);

        $this->get(route('jogos.conferencia', $noite))
            ->assertSeeInOrder(['Estrutura de blinds', 'Digitada na noite'])
            ->assertDontSee('Blinds · ');
    }

    public function test_fluxo_da_noite_marca_a_etapa_atual(): void
    {
        $noite = $this->noiteAgendada();

        $this->get(route('jogos.conferencia', $noite))
            ->assertSee('aria-current="step"', false)
            ->assertSeeInOrder(['Antes', 'Conferência', 'Jogadores', 'Mesas', 'Durante', 'Ao vivo', 'Resumo', 'Final', 'Premiação', 'Resultado', 'Fechamento']);
    }

    public function test_cash_game_mostra_recompra_e_valor_da_ficha(): void
    {
        $jogo = Jogo::factory()->cashGame()->agendado()->comEntradasPadrao('200.00', 20000)->create(['nome' => 'Cash de quarta']);
        $jogo->itens()->where('tipo', TipoItem::Addon)->delete();
        $jogo->itens()->create([
            'tipo' => TipoItem::Opcional, 'nome' => 'Churrasco', 'valor' => 30,
            'percentual_premiacao' => 0, 'compravel_com' => ['jogador'], 'obrigatorio' => true,
        ]);

        $this->get(route('jogos.conferencia', $jogo))
            ->assertOk()
            ->assertSee('Antes do jogo · 1 de 3')
            ->assertSee('Recompra')
            ->assertDontSee('Add-on')
            ->assertSee('R$ 0,01')
            ->assertSee('Cash-out: fichas × valor')
            ->assertSeeInOrder(['Opcionais', 'Churrasco', '1 por jogador', 'não vai para a premiação', 'obrigatório'])
            ->assertDontSee('Premiação</dt>', false);
    }

    public function test_jogo_em_andamento_nao_abre_a_conferencia(): void
    {
        $noite = Jogo::query()->where('nome', PokerNight18Seeder::NOME)->sole();

        // Volta para a etapa atual (ao vivo), com o aviso.
        $this->get(route('jogos.conferencia', $noite))
            ->assertRedirect(route('jogos.ao-vivo', $noite))
            ->assertSessionHas('aviso', 'Conferência não abre agora: o jogo está em andamento.');

        $this->followingRedirects()
            ->get(route('jogos.conferencia', $noite))
            ->assertSee('Conferência não abre agora: o jogo está em andamento.');
    }

    public function test_jogo_fora_do_site_volta_para_a_lista(): void
    {
        $rascunho = Jogo::factory()->create(['nome' => 'Especial Halloween']);
        $modelo = Jogo::factory()->modelo()->agendado()->create(['nome' => 'Modelo de sábado']);

        $this->get(route('jogos.conferencia', $rascunho))
            ->assertRedirect(route('jogos.index'))
            ->assertSessionHas('aviso', 'Especial Halloween não está disponível no site.');

        $this->get(route('jogos.conferencia', $modelo))->assertRedirect(route('jogos.index'));
    }

    public function test_abrir_na_lista_leva_para_a_conferencia(): void
    {
        $noite = $this->noiteAgendada();

        $this->get(route('jogos.index'))
            ->assertSee('href="'.route('jogos.conferencia', $noite).'"', false);
    }
}
