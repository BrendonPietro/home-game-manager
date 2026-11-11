<?php

namespace Tests\Feature;

use App\Models\EstruturaBlinds;
use App\Models\Jogo;
use App\Models\RegraPremiacao;
use App\Models\Seguranca;
use App\Models\Usuario;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PokerNight18Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O db:seed deixa o banco pronto para usar de verdade (o mesmo que vai para produção): permissões, regras
 * de premiação, estruturas de blinds e as contas do clube. Os dados de exemplo só no local com DADOS_DE_EXEMPLO.
 */
class BancoLimpoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['env'] = 'local';
        config([
            'clube.desenvolvedor' => 'Fulano de Tal <FulanoDeTal>',
            'clube.desenvolvedor_senha' => 'senha-do-dev',
            'clube.admins' => 'Ciclano Souza <CiclanoSouza>',
            'clube.admins_senha' => 'senha-dos-admins',
        ]);
    }

    public function test_sem_dados_de_exemplo_o_banco_fica_so_com_o_essencial(): void
    {
        config(['clube.dados_de_exemplo' => false]);

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(['ciclanosouza', 'fulanodetal'], Usuario::query()->orderBy('login')->pluck('login')->all());
        $this->assertSame(['ciclanosouza', 'fulanodetal'], Seguranca::query()->orderBy('login')->pluck('login')->all());
        $this->assertSame(0, Jogo::query()->count());
        $this->assertSame(['Deepstack 20 min', 'Turbo 15 min'], EstruturaBlinds::query()->orderBy('nome')->pluck('nome')->all());
        $this->assertGreaterThan(0, RegraPremiacao::query()->count());

        // Rodar de novo (ex.: depois de uma atualização) não duplica nem traz exemplo.
        $this->seed(DatabaseSeeder::class);
        $this->assertSame([2, 2, 0, 2], [Usuario::query()->count(), Seguranca::query()->count(), Jogo::query()->count(), EstruturaBlinds::query()->count()]);
    }

    public function test_dados_de_exemplo_so_quando_pedidos(): void
    {
        config(['clube.dados_de_exemplo' => true]);

        $this->seed(DatabaseSeeder::class);

        $this->assertTrue(Jogo::query()->where('nome', PokerNight18Seeder::NOME)->exists());
        $this->assertTrue(Seguranca::query()->where('login', 'dev')->exists());
        $this->assertTrue(Usuario::query()->where('email', 'dev@rodrigues.test')->exists());
    }
}
