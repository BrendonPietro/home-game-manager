<?php

namespace Tests\Feature\Site;

use App\Enums\StatusJogo;
use App\Enums\StatusParticipante;
use App\Livewire\Jogo\AoVivo;
use App\Models\Jogo;
use App\Models\JogoMesa;
use App\Models\Participante;
use App\Models\Usuario;
use Database\Seeders\PermissoesSeeder;
use Database\Seeders\PokerNight18Seeder;
use Database\Seeders\RegrasPremiacaoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AoVivoTest extends TestCase
{
    use RefreshDatabase;

    private Jogo $noite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed([PermissoesSeeder::class, RegrasPremiacaoSeeder::class, PokerNight18Seeder::class]);

        $usuario = Usuario::factory()->comAcesso()->create();
        $usuario->assignRole('Operador');
        $this->actingAs($usuario, 'web');

        $this->noite = Jogo::query()->where('nome', PokerNight18Seeder::NOME)->sole();
    }

    private function jogador(string $nome): Participante
    {
        return $this->noite->participantes()->whereHas('usuario', fn ($q) => $q->where('nome', $nome))->sole();
    }

    private function mesa(int $ordem): JogoMesa
    {
        return $this->noite->mesas()->where('ordem', $ordem)->sole();
    }

    public function test_faixa_mesa_e_lateral_da_noite_18(): void
    {
        $this->get(route('jogos.ao-vivo', $this->noite))
            ->assertOk()
            ->assertSee('wire:poll.5s', false)
            // Faixa: nível, fase, jogando, entradas, arrecadação e prize pool.
            ->assertSeeInOrder(['Nível 6', '100 / 200', 'Fase', 'Intervalo (add-on)', 'Jogando', '12', 'Entradas', '22', 'Arrecadação', 'R$ 2.100', 'Prize pool', 'R$ 1.890'])
            ->assertSeeInOrder(['Mesa 01 · 7', 'Mesa 02 · 5'])
            ->assertSee('Blinds 100 / 200')
            // Assento do João (buy-in + add-on) e do Carlos (buy-in, rebuy e add-on).
            ->assertSeeInOrder(['João', 'R$ 150', '>B<', '>A<'], false)
            ->assertSeeInOrder(['Carlos', 'R$ 250', '>B<', '>R<', '>A<'], false)
            // Lateral: prize pool, taxas, contagem e o último registro (add-on do Marcão).
            ->assertSee('Arrecadado R$ 2.100 · taxas R$ 210')
            ->assertSeeInOrder(['Buy-ins', '12', 'Rebuys', '6', 'Add-ons', '4'])
            ->assertSeeInOrder(['Últimos registros', 'Marcão', '· Add-on', 'R$ 50']);
    }

    public function test_buyin_duplo_aparece_como_2(): void
    {
        $this->get(route('jogos.ao-vivo', $this->noite))
            ->assertSeeInOrder(['Rafael', 'R$ 200', '>2<'], false);
    }

    public function test_trocar_de_mesa_e_selecionar_jogador(): void
    {
        $brendon = $this->jogador('Brendon');

        Livewire::test(AoVivo::class, ['jogo' => $this->noite])
            ->assertDontSee('Lugar 1: Brendon')
            ->call('verMesa', $this->mesa(2)->id)
            ->assertSet('mesa', $this->mesa(2)->id)
            ->assertSee('Lugar 1: Brendon')
            ->call('clicarAssento', $this->mesa(2)->id, 1)
            ->assertSet('selecionado', $brendon->id)
            ->call('clicarAssento', $this->mesa(2)->id, 1)
            ->assertSet('selecionado', null);
    }

    public function test_quem_saiu_aparece_apagado_e_quem_esta_sem_lugar_embaixo(): void
    {
        $this->jogador('João')->forceFill(['status' => StatusParticipante::Eliminado])->save();
        $this->noite->participantes()->create(['usuario_id' => Usuario::factory()->create(['nome' => 'Zeca', 'apelido' => null])->id]);

        Livewire::test(AoVivo::class, ['jogo' => $this->noite])
            ->assertSeeHtml('rh-seat rh-seat--out')
            ->assertSeeInOrder(['Sem lugar', 'Zeca'])
            ->assertSeeInOrder(['Jogando', '11']);
    }

    public function test_cash_game_mostra_recompras_e_cash_outs(): void
    {
        $cash = Jogo::factory()->cashGame()->emAndamento()->comEntradasPadrao()->create(['blinds_atual' => '1 / 2']);

        $this->get(route('jogos.ao-vivo', $cash))
            ->assertOk()
            ->assertSeeInOrder(['Blinds', '1 / 2', 'Jogando'])
            ->assertDontSee('Fase')
            ->assertSeeInOrder(['Buy-ins', 'Recompras', 'Cash-outs'])
            ->assertSee('Nenhuma mesa neste jogo')
            ->assertSee('Nenhuma entrada ainda');
    }

    public function test_jogo_agendado_vai_para_a_conferencia(): void
    {
        $agendado = Jogo::factory()->agendado()->create();

        $this->get(route('jogos.ao-vivo', $agendado))->assertRedirect(route('jogos.conferencia', $agendado));
    }

    public function test_jogo_que_deixou_de_estar_em_andamento_sai_da_tela(): void
    {
        $tela = Livewire::test(AoVivo::class, ['jogo' => $this->noite]);

        $this->noite->forceFill(['status' => StatusJogo::Finalizado])->save();

        // Vai para a etapa atual do jogo finalizado: o resultado.
        $tela->call('selecionar', null)->assertRedirect(route('jogos.resultado', $this->noite));
        $this->assertSame('Poker Night #18 não está mais em andamento.', session('aviso'));
    }

    public function test_abrir_o_jogo_em_andamento_leva_para_o_ao_vivo(): void
    {
        $this->get(route('jogos.index', ['aba' => 'andamento']))
            ->assertSee('href="'.route('jogos.ao-vivo', $this->noite).'"', false);
    }
}
