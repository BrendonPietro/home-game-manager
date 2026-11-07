<?php

namespace Tests\Feature\Jogo;

use App\Actions\Jogo\CriarDeModelo;
use App\Enums\CalculoTaxa;
use App\Enums\Periodicidade;
use App\Enums\StatusJogo;
use App\Enums\TipoItem;
use App\Models\Jogo;
use App\Models\Mesa;
use App\Models\Ranking;
use App\Models\Usuario;
use App\Services\Jogo\CopiaDoJogo;
use Database\Seeders\PokerNight18Seeder;
use Database\Seeders\RegrasPremiacaoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CopiaDoJogoTest extends TestCase
{
    use RefreshDatabase;

    private function modelo(): Jogo
    {
        $modelo = Jogo::factory()->modelo('Torneio de sábado')->comEntradasPadrao()->create(['horario' => '19:30']);
        $modelo->itens()->create(['tipo' => TipoItem::Opcional, 'nome' => 'Churrasco', 'valor' => 30, 'compravel_com' => ['jogador'], 'percentual_premiacao' => 0]);
        $modelo->taxas()->create(['nome' => 'Dealers', 'calculo' => CalculoTaxa::PorUnidade, 'valor' => 215, 'quantidade' => 2]);
        $modelo->premiacoes()->createMany([['posicao' => 1, 'percentual' => 60], ['posicao' => 2, 'percentual' => 40]]);
        $modelo->mesas()->create(['mesa_id' => Mesa::factory()->create()->id, 'dealer_id' => Usuario::factory()->dealer()->create()->id, 'lugares' => 8, 'ordem' => 1]);
        $modelo->rankings()->attach(Ranking::query()->create(['nome' => 'Anual', 'periodicidade' => Periodicidade::Anual]));

        return $modelo;
    }

    public function test_criar_jogo_a_partir_do_modelo(): void
    {
        $modelo = $this->modelo();

        $jogo = app(CriarDeModelo::class)($modelo, 'Poker Night #20', '2026-11-07');

        $this->assertFalse($jogo->e_modelo);
        $this->assertNull($jogo->nome_modelo);
        $this->assertSame(StatusJogo::Rascunho, $jogo->status);
        $this->assertSame(['Poker Night #20', '2026-11-07', '19:30'], [$jogo->nome, $jogo->data->toDateString(), substr($jogo->horario, 0, 5)]);
        $this->assertSame($modelo->itens()->pluck('nome')->all(), $jogo->itens()->pluck('nome')->all());
        $this->assertSame(4, $jogo->itens()->count());
        $this->assertSame([['Dealers', 2]], $jogo->taxas->map(fn ($t) => [$t->nome, $t->quantidade])->all());
        $this->assertSame([[1, '60.00', null], [2, '40.00', null]], $jogo->premiacoes->map(fn ($p) => [$p->posicao, $p->percentual, $p->valor])->all());
        $this->assertSame([8], $jogo->mesas()->pluck('lugares')->all());
        $this->assertSame(['Anual'], $jogo->rankings()->pluck('nome')->all());
        $this->assertNotNull($jogo->evento); // com data, entra na agenda

        // O modelo continua igual.
        $this->assertSame(4, $modelo->itens()->count());
        $this->assertTrue($modelo->fresh()->e_modelo);
    }

    public function test_so_cria_a_partir_de_modelo(): void
    {
        $this->expectExceptionMessage('Esse jogo não é um modelo.');

        app(CriarDeModelo::class)(Jogo::factory()->create(), 'X', '2026-11-07');
    }

    public function test_duplicar_e_salvar_como_modelo_nao_levam_a_noite(): void
    {
        $this->seed([RegrasPremiacaoSeeder::class, PokerNight18Seeder::class]);
        $noite = Jogo::query()->where('nome', PokerNight18Seeder::NOME)->sole();

        $copia = app(CopiaDoJogo::class)->copiar($noite, ['nome' => 'Poker Night #18 (cópia)']);
        $modelo = app(CopiaDoJogo::class)->copiar($noite, ['nome' => 'Domingo', 'nome_modelo' => 'Domingo', 'e_modelo' => true]);

        foreach ([$copia, $modelo] as $jogo) {
            $this->assertSame(StatusJogo::Rascunho, $jogo->status);
            $this->assertNull($jogo->fase);
            $this->assertNull($jogo->data);
            $this->assertNull($jogo->nivel_atual);
            $this->assertSame(0, $jogo->participantes()->count() + $jogo->entradas()->count());
            $this->assertSame(5, $jogo->itens()->count());
            $this->assertSame(2, $jogo->mesas()->count());
            $this->assertSame($noite->estrutura_blinds_id, $jogo->estrutura_blinds_id); // Turbo 15 min
            $this->assertNull($jogo->evento); // sem data (cópia) ou modelo: fora da agenda
        }

        $this->assertTrue($modelo->e_modelo);
        $this->assertFalse(Jogo::query()->noSite()->whereKey($modelo->id)->exists());
    }
}
