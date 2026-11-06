<?php

namespace Tests\Feature\Site;

use App\Actions\Jogo\FecharEntradas;
use App\Actions\Jogo\RegistrarEliminacao;
use App\Models\Jogo;
use App\Models\Usuario;
use Database\Seeders\PermissoesSeeder;
use Database\Seeders\PokerNight18Seeder;
use Database\Seeders\RegrasPremiacaoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PremiacaoTest extends TestCase
{
    use RefreshDatabase;

    private Jogo $noite;

    private Usuario $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed([PermissoesSeeder::class, RegrasPremiacaoSeeder::class, PokerNight18Seeder::class]);
        $this->admin = Usuario::factory()->comAcesso()->create();
        $this->admin->assignRole('Admin');
        $this->actingAs($this->admin, 'web');

        $this->noite = Jogo::query()->where('nome', PokerNight18Seeder::NOME)->sole();
    }

    public function test_so_abre_depois_de_fechar_as_entradas(): void
    {
        $this->get(route('jogos.premiacao', $this->noite))
            ->assertRedirect(route('jogos.ao-vivo', $this->noite))
            ->assertSessionHas('aviso', 'Premiação abre depois de fechar as entradas.');
    }

    public function test_do_arrecadado_ao_premio_da_noite_18(): void
    {
        app(FecharEntradas::class)($this->noite, $this->admin);

        $this->get(route('jogos.premiacao', $this->noite))
            ->assertOk()
            ->assertSee('Final · entradas fechadas às')
            ->assertSeeInOrder(['Total arrecadado', 'R$ 2.100', 'Casa (10%)', '−R$ 210', 'Valor retirado antes da premiação', 'R$ 210', 'Prize pool', 'R$ 1.890'])
            ->assertSeeInOrder(['3 posições premiadas', 'Regra: 50 / 30 / 20'])
            ->assertSeeInOrder(['1º lugar · 50%', 'em jogo', 'R$ 950', '2º lugar · 30%', 'R$ 565', '3º lugar · 20%', 'R$ 375'])
            ->assertSee('R$ 1.890 · 100%')
            ->assertSee('Prêmios arredondados para R$ 5: a diferença foi para o 1º.')
            ->assertSeeInOrder(['Regra de distribuição', 'até 10', '65% · 35%', '11 a 18', 'hoje: 12', '50% · 30% · 20%', '19 ou mais', '45% · 27% · 17% · 11%']);
    }

    public function test_mostra_quem_ja_esta_na_posicao(): void
    {
        app(FecharEntradas::class)($this->noite, $this->admin);
        $eliminar = fn (string $nome) => app(RegistrarEliminacao::class)(
            $this->noite->participantes()->whereHas('usuario', fn ($q) => $q->where('nome', $nome))->sole(),
            $this->admin,
        );

        foreach (['João', 'Lucas', 'Carlos', 'Rafael', 'André', 'Bruno', 'Pedro', 'Brendon', 'Thiago', 'Diego'] as $nome) {
            $eliminar($nome); // Diego cai em 3º
        }

        $this->get(route('jogos.premiacao', $this->noite))
            ->assertSeeInOrder(['1º lugar', 'em jogo', '2º lugar', 'em jogo', '3º lugar', 'Diego', 'R$ 375']);
    }

    public function test_cash_game_nao_tem_premiacao(): void
    {
        $cash = Jogo::factory()->cashGame()->emAndamento()->create();

        $this->get(route('jogos.premiacao', $cash))->assertRedirect(route('jogos.ao-vivo', $cash));
    }
}
