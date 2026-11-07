<?php

namespace Tests\Feature\Jogo;

use App\Actions\Jogo\AbrirIntervalo;
use App\Actions\Jogo\ConfirmarJogador;
use App\Actions\Jogo\DesfazerAcao;
use App\Actions\Jogo\FecharEntradas;
use App\Actions\Jogo\FinalizarJogo;
use App\Actions\Jogo\IniciarJogo;
use App\Actions\Jogo\RegistrarEliminacao;
use App\Actions\Jogo\RegistrarEntrada;
use App\Actions\Jogo\TrocarLugar;
use App\Enums\CalculoTaxa;
use App\Enums\Periodicidade;
use App\Enums\TipoItem;
use App\Models\Item;
use App\Models\Jogo;
use App\Models\Mesa;
use App\Models\Participante;
use App\Models\Ranking;
use App\Models\Usuario;
use App\Services\Jogo\Calculadora;
use App\Services\Jogo\Saldos;
use App\Support\Dinheiro;
use Database\Seeders\PermissoesSeeder;
use Database\Seeders\PokerNight18Seeder;
use Database\Seeders\RegrasPremiacaoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * A Poker Night #18 do começo ao fim só pelas Actions, como o site vai fazer: confirmar, sentar,
 * iniciar, buy-ins, rebuys, encerrar rebuys, add-ons, fechar entradas e eliminar até sobrar um.
 * Tem de chegar em R$ 2.100 / 210 / 1.890 → 950 / 565 / 375 (prêmios arredondados para R$ 5).
 */
class NoiteDeReferenciaTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_noite_18_inteira_pelas_actions(): void
    {
        $this->seed([PermissoesSeeder::class, RegrasPremiacaoSeeder::class]);
        $admin = Usuario::factory()->comAcesso()->create();
        $admin->assignRole('Admin');
        $operador = Usuario::factory()->comAcesso()->create();
        $operador->assignRole('Operador');

        $jogo = Jogo::factory()->agendado()->create(['nome' => PokerNight18Seeder::NOME]);
        $item = fn (TipoItem $tipo, string $nome, int $valor, ?int $limite, bool $padrao = true): Item => $jogo->itens()->create([
            'tipo' => $tipo, 'nome' => $nome, 'valor' => $valor, 'fichas' => 10000, 'limite_por_jogador' => $limite, 'padrao' => $padrao,
        ]);
        $itens = [
            'simples' => $item(TipoItem::Buyin, 'Buy-in', 100, 1),
            'rebuy' => $item(TipoItem::Rebuy, 'Rebuy', 100, null),
            'addon' => $item(TipoItem::Addon, 'Add-on', 50, 1),
            'duplo' => $item(TipoItem::Buyin, 'Buy-in duplo', 200, 1, false),
        ];
        $jogo->taxas()->create(['nome' => 'Casa', 'calculo' => CalculoTaxa::Percentual, 'valor' => 10]);
        $mesas = [];
        foreach ([1, 2] as $numero) {
            $mesas[$numero] = $jogo->mesas()->create(['mesa_id' => Mesa::factory()->create(['nome' => "Mesa 0{$numero}"])->id, 'lugares' => 9, 'ordem' => $numero]);
        }

        // Antes da noite: lista e lugares do protótipo.
        $jogadores = [];
        foreach (PokerNight18Seeder::JOGADORES as [$nome, $apelido, $mesa, $lugar, $buyin, $rebuys, $addons]) {
            $participante = app(ConfirmarJogador::class)($jogo, Usuario::factory()->create(['nome' => $nome, 'apelido' => $apelido]), $operador);
            app(TrocarLugar::class)($participante, $mesas[$mesa], $lugar, $admin);
            $jogadores[$nome] = [$participante, $buyin, $rebuys, $addons];
        }

        // Iniciar já lança o buy-in padrão de cada sentado (sentar é entrar).
        app(IniciarJogo::class)($jogo, $admin);
        $this->assertSame(12, $jogo->entradas()->where('tipo', TipoItem::Buyin)->where('jogo_item_id', $itens['simples']->id)->count());

        $lancar = fn (Participante $participante, Item $item) => app(RegistrarEntrada::class)($participante, $item, $operador, (string) Str::uuid());

        foreach ($jogadores as [$participante, $buyin, $rebuys]) {
            // Outra variação (buy-in duplo): desfaz o padrão e lança a certa.
            if ($buyin !== 'simples') {
                app(DesfazerAcao::class)($participante, 'Buy-in duplo', $admin);
                $lancar($participante, $itens[$buyin]);
            }

            for ($i = 0; $i < $rebuys; $i++) {
                $lancar($participante, $itens['rebuy']);
            }
        }

        app(AbrirIntervalo::class)($jogo, $admin);

        foreach ($jogadores as [$participante, , , $addons]) {
            for ($i = 0; $i < $addons; $i++) {
                $lancar($participante, $itens['addon']);
            }
        }

        $resumo = app(Calculadora::class)->doJogo($jogo);
        $this->assertSame([210000, 21000, 189000, 22, 12], [$resumo->arrecadacao, $resumo->totalTaxas, $resumo->prizePool, $resumo->entradas, $resumo->jogadores]);

        app(FecharEntradas::class)($jogo, $admin);
        $this->assertSame([1 => '950.00', 2 => '565.00', 3 => '375.00'], $jogo->premiacoes()->pluck('valor', 'posicao')->all());

        // Caem na ordem da lista até sobrar o Marcão.
        $ordem = array_values(array_diff(array_keys($jogadores), ['Marcos']));
        foreach ($ordem as $nome) {
            app(RegistrarEliminacao::class)($jogadores[$nome][0], $operador);
        }

        $final = $jogo->participantes()->with('usuario')->get()->keyBy('usuario.nome');
        foreach ($ordem as $indice => $nome) {
            $this->assertSame(12 - $indice, $final[$nome]->posicao_final, $nome);
        }
        $this->assertSame([1, '950.00'], [$final['Marcos']->posicao_final, $final['Marcos']->premio]);
        $this->assertSame(['565.00', '375.00', null], [$final['Felipe']->premio, $final['Diego']->premio, $final['Thiago']->premio]);

        // Sem pagamentos, a soma dos saldos é o que fica com a casa: a taxa de R$ 210.
        $saldos = app(Saldos::class)->doJogo($jogo);
        $this->assertSame(21000, $saldos->sum('saldo'));
        $this->assertSame(25000 - 95000, $saldos[$final['Marcos']->id]->saldo);

        // Finalizar: os acertos fecham o saldo de cada um e levam a taxa para a casa; os pontos saem da posição.
        $ranking = Ranking::query()->create([
            'nome' => 'Anual 2026', 'periodicidade' => Periodicidade::Anual, 'ft_pontua' => true, 'pontos_mesa_final' => 2,
            'participacao_pontua' => true, 'pontos_participacao' => 1,
        ]);
        $ranking->pontuacoes()->createMany([['posicao' => 1, 'pontos' => 10], ['posicao' => 2, 'pontos' => 6], ['posicao' => 3, 'pontos' => 4]]);
        $jogo->rankings()->attach($ranking);

        app(FinalizarJogo::class)($jogo, $admin);

        $liquido = [];
        foreach ($jogo->acertos()->get() as $acerto) {
            $valor = Dinheiro::centavos($acerto->valor);
            $liquido[$acerto->de_participante_id ?? 'casa'] = ($liquido[$acerto->de_participante_id ?? 'casa'] ?? 0) + $valor;
            $liquido[$acerto->para_participante_id ?? 'casa'] = ($liquido[$acerto->para_participante_id ?? 'casa'] ?? 0) - $valor;
        }
        $this->assertSame(-21000, $liquido['casa']);
        foreach ($saldos as $id => $saldo) {
            $this->assertSame($saldo->saldo, $liquido[$id] ?? 0, "acertos do participante {$id}");
        }

        $pontos = $ranking->pontos()->with('usuario')->get()->pluck('pontos', 'usuario.nome');
        $this->assertSame([13, 9, 7, 3, 1, 1], [$pontos['Marcos'], $pontos['Felipe'], $pontos['Diego'], $pontos['Rafael'], $pontos['Carlos'], $pontos['João']]);
    }
}
