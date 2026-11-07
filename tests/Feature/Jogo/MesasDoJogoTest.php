<?php

namespace Tests\Feature\Jogo;

use App\Actions\Jogo\AbrirIntervalo;
use App\Actions\Jogo\AdicionarMesa;
use App\Actions\Jogo\AjustarMesa;
use App\Actions\Jogo\FecharEntradas;
use App\Actions\Jogo\IniciarJogo;
use App\Actions\Jogo\RemoverMesa;
use App\Actions\Jogo\SortearLugares;
use App\Actions\Jogo\TrocarLugar;
use App\Enums\FaseTorneio;
use App\Enums\StatusJogo;
use App\Enums\StatusParticipante;
use App\Enums\TipoItem;
use App\Exceptions\RegraDoJogo;
use App\Models\Jogo;
use App\Models\JogoMesa;
use App\Models\Mesa;
use App\Models\Participante;
use App\Models\Usuario;
use App\Services\Jogo\Calculadora;
use App\Services\Jogo\EventoDoJogo;
use Database\Seeders\PermissoesSeeder;
use Database\Seeders\RegrasPremiacaoSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MesasDoJogoTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $admin;

    private Jogo $jogo;

    private JogoMesa $mesa1;

    private JogoMesa $mesa2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissoesSeeder::class);
        $this->admin = Usuario::factory()->comAcesso()->create();
        $this->admin->assignRole('Admin');

        $this->jogo = Jogo::factory()->agendado()->comEntradasPadrao()->create();
        $this->mesa1 = $this->jogo->mesas()->create(['nome' => 'Mesa 01', 'lugares' => 9, 'ordem' => 1]);
        $this->mesa2 = $this->jogo->mesas()->create(['nome' => 'Mesa 02', 'lugares' => 9, 'ordem' => 2]);
    }

    private function confirmado(?JogoMesa $mesa = null, ?int $lugar = null): Participante
    {
        return $this->jogo->participantes()->create([
            'usuario_id' => Usuario::factory()->create()->id,
            'jogo_mesa_id' => $mesa?->id,
            'lugar' => $lugar,
        ]);
    }

    private function trocar(Participante $p, ?JogoMesa $mesa, ?int $lugar): void
    {
        app(TrocarLugar::class)($p, $mesa, $lugar, $this->admin);
    }

    private function lugarDe(Participante $p): array
    {
        $p->refresh();

        return [$p->jogo_mesa_id, $p->lugar];
    }

    public function test_mover_trocar_e_tirar_do_lugar(): void
    {
        $joao = $this->confirmado($this->mesa1, 1);
        $lucas = $this->confirmado($this->mesa1, 2);
        $zeca = $this->confirmado();

        $this->trocar($joao, $this->mesa2, 5); // lugar vazio
        $this->assertSame([$this->mesa2->id, 5], $this->lugarDe($joao));

        $this->trocar($zeca, $this->mesa1, 2); // ocupado pelo Lucas: Lucas vai para onde Zeca estava (sem lugar)
        $this->assertSame([$this->mesa1->id, 2], $this->lugarDe($zeca));
        $this->assertSame([null, null], $this->lugarDe($lucas));

        $this->trocar($zeca, $this->mesa2, 5); // troca com o João
        $this->assertSame([$this->mesa2->id, 5], $this->lugarDe($zeca));
        $this->assertSame([$this->mesa1->id, 2], $this->lugarDe($joao));

        $this->trocar($zeca, null, null);
        $this->assertSame([null, null], $this->lugarDe($zeca));
    }

    public function test_lugar_fora_da_mesa_ou_mesa_de_outro_jogo(): void
    {
        $joao = $this->confirmado();
        $outra = Jogo::factory()->agendado()->create()->mesas()->create(['nome' => 'Mesa 1', 'lugares' => 9]);

        foreach ([[$this->mesa1, 10, 'A Mesa 01 tem 9 lugares.'], [$outra, 1, 'Essa mesa não é deste jogo.']] as [$mesa, $lugar, $mensagem]) {
            try {
                $this->trocar($joao, $mesa, $lugar);
                $this->fail('Deixou sentar');
            } catch (RegraDoJogo $erro) {
                $this->assertSame($mensagem, $erro->getMessage());
            }
        }
    }

    public function test_operador_nao_organiza_mesas(): void
    {
        $operador = Usuario::factory()->comAcesso()->create();
        $operador->assignRole('Operador');

        $this->expectException(AuthorizationException::class);

        app(TrocarLugar::class)($this->confirmado(), $this->mesa1, 1, $operador);
    }

    public function test_sortear_equilibra_as_mesas(): void
    {
        foreach (range(1, 13) as $_) {
            $this->confirmado();
        }

        app(SortearLugares::class)($this->jogo, $this->admin);

        $sentados = $this->jogo->participantes()->get();
        $this->assertTrue($sentados->every(fn ($p) => $p->jogo_mesa_id !== null && $p->lugar >= 1 && $p->lugar <= 9));
        $this->assertSame([7, 6], [$sentados->where('jogo_mesa_id', $this->mesa1->id)->count(), $sentados->where('jogo_mesa_id', $this->mesa2->id)->count()]);
        $this->assertSame(13, $sentados->map(fn ($p) => $p->jogo_mesa_id.'-'.$p->lugar)->unique()->count());
    }

    public function test_sortear_de_novo_quem_ja_estava_sentado_na_mesma_mesa(): void
    {
        // Só uma mesa: todo mundo cai na mesma mesa em que já estava.
        $this->mesa2->delete();
        $joao = $this->confirmado($this->mesa1, 1);
        $lucas = $this->confirmado($this->mesa1, 2);

        app(SortearLugares::class)($this->jogo, $this->admin);

        $this->assertSame($this->mesa1->id, $joao->fresh()->jogo_mesa_id);
        $this->assertSame($this->mesa1->id, $lucas->fresh()->jogo_mesa_id);
    }

    public function test_sortear_sem_lugar_suficiente_ou_sem_mesa(): void
    {
        foreach (range(1, 19) as $_) {
            $this->confirmado();
        }

        try {
            app(SortearLugares::class)($this->jogo, $this->admin);
            $this->fail('Sorteou sem lugar');
        } catch (RegraDoJogo $erro) {
            $this->assertSame('Faltam 1 lugares: adicione uma mesa ou aumente os lugares.', $erro->getMessage());
        }

        $semMesa = Jogo::factory()->agendado()->create();
        $this->expectExceptionMessage('Adicione uma mesa antes de sortear.');
        app(SortearLugares::class)($semMesa, $this->admin);
    }

    public function test_ajustar_mesa(): void
    {
        $this->confirmado($this->mesa1, 8);
        $sergio = Usuario::factory()->dealer()->create(['nome' => 'Sérgio', 'apelido' => null]);
        $jogador = Usuario::factory()->create(['nome' => 'Bruno', 'apelido' => null]);

        app(AjustarMesa::class)($this->mesa1, 8, $sergio, $this->admin);
        $this->assertSame([8, $sergio->id], [$this->mesa1->fresh()->lugares, $this->mesa1->fresh()->dealer_id]);

        foreach ([[6, null, 'Tem jogador no lugar 8: mude-o antes de diminuir a mesa.'], [9, $jogador, 'Bruno não está marcado como dealer no cadastro.']] as [$lugares, $dealer, $mensagem]) {
            try {
                app(AjustarMesa::class)($this->mesa1, $lugares, $dealer, $this->admin);
                $this->fail('Ajustou');
            } catch (RegraDoJogo $erro) {
                $this->assertSame($mensagem, $erro->getMessage());
            }
        }
    }

    public function test_adicionar_e_remover_mesa_sem_o_cadastro_de_mesas(): void
    {
        app(EventoDoJogo::class)->sincronizar($this->jogo);
        $this->jogo->forceFill(['lugares_por_mesa' => 8])->save();

        // Não precisa de mesa cadastrada: "Mesa 3" (depois da Mesa 02) com os lugares por mesa do jogo.
        $nova = app(AdicionarMesa::class)($this->jogo, $this->admin);
        $this->assertSame(['Mesa 3', 8, 3], [$nova->nome, $nova->lugares, $nova->ordem]);
        $this->assertSame('Mesa 4', app(AdicionarMesa::class)($this->jogo, $this->admin)->nome);
        $this->assertSame(0, Mesa::query()->count());

        // O jogo não ocupa mesas do cadastro na agenda.
        $this->assertSame([], $this->jogo->evento->mesas()->pluck('mesas.id')->all());

        app(RemoverMesa::class)($nova, $this->admin);
        $this->assertSame(['Mesa 01', 'Mesa 02', 'Mesa 4'], $this->jogo->mesas()->pluck('nome')->all());

        $this->confirmado($this->mesa2, 1);
        $this->expectExceptionMessage('Tire os jogadores da Mesa 02 antes de removê-la.');
        app(RemoverMesa::class)($this->mesa2, $this->admin);
    }

    public function test_proximo_nome_de_mesa(): void
    {
        $this->assertSame('Mesa 1', AdicionarMesa::proximoNome([]));
        $this->assertSame('Mesa 3', AdicionarMesa::proximoNome(['Mesa 01', 'Mesa 2']));
        $this->assertSame('Mesa 3', AdicionarMesa::proximoNome(['Final', 'Principal']));
        $this->assertSame('Mesa 8', AdicionarMesa::proximoNome(['Mesa 7']));
    }

    public function test_iniciar_o_torneio(): void
    {
        $joao = $this->confirmado($this->mesa1, 1);

        try {
            app(IniciarJogo::class)($this->jogo, $this->admin);
            $this->fail('Iniciou com 1 jogador');
        } catch (RegraDoJogo $erro) {
            $this->assertSame('Sente pelo menos 2 jogadores antes de iniciar.', $erro->getMessage());
        }

        $lucas = $this->confirmado($this->mesa2, 3);
        $zeca = $this->confirmado();

        app(IniciarJogo::class)($this->jogo, $this->admin);

        $jogo = $this->jogo->fresh();
        $this->assertSame(StatusJogo::EmAndamento, $jogo->status);
        $this->assertSame(FaseTorneio::Rebuys, $jogo->fase);
        $this->assertNotNull($jogo->iniciado_em);
        $this->assertSame(StatusParticipante::Jogando, $joao->fresh()->status);
        $this->assertSame(StatusParticipante::Jogando, $lucas->fresh()->status);
        $this->assertSame(StatusParticipante::Confirmado, $zeca->fresh()->status);

        // Sentar é entrar: cada sentado já tem o buy-in padrão; quem está sem lugar não.
        $this->assertSame([TipoItem::Buyin], $joao->entradas()->validas()->pluck('tipo')->all());
        $this->assertSame(1, $lucas->entradas()->validas()->count());
        $this->assertSame(0, $zeca->entradas()->count());
        $this->assertSame(20000, app(Calculadora::class)->doJogo($jogo)->arrecadacao);
    }

    public function test_quem_senta_com_o_torneio_em_andamento_ja_entra(): void
    {
        $this->confirmado($this->mesa1, 1);
        $this->confirmado($this->mesa1, 2);
        app(IniciarJogo::class)($this->jogo, $this->admin);

        // Chegou atrasado: senta e já tem o buy-in (com o obrigatório, se tiver).
        $zeca = $this->confirmado();
        $this->trocar($zeca, $this->mesa2, 5);

        $this->assertSame(StatusParticipante::Jogando, $zeca->fresh()->status);
        $this->assertSame(1, $zeca->entradas()->validas()->where('tipo', TipoItem::Buyin)->count());

        // Mudar de lugar (quebra de mesa) não lança de novo.
        $this->trocar($zeca, $this->mesa1, 7);
        $this->assertSame(1, $zeca->entradas()->count());

        // Com as entradas fechadas, ninguém novo senta.
        $this->seed(RegrasPremiacaoSeeder::class);
        app(AbrirIntervalo::class)($this->jogo, $this->admin);
        app(FecharEntradas::class)($this->jogo, $this->admin);
        $tarde = $this->confirmado();

        try {
            $this->trocar($tarde, $this->mesa2, 1);
            $this->fail('Sentou com as entradas fechadas');
        } catch (RegraDoJogo $erro) {
            $this->assertStringStartsWith('As entradas já foram fechadas', $erro->getMessage());
        }

        $this->assertSame([null, null], $this->lugarDe($tarde));
        $this->assertSame(0, $tarde->entradas()->count());
    }

    public function test_cash_game_inicia_sem_fase_e_operador_nao_inicia(): void
    {
        $cash = Jogo::factory()->cashGame()->agendado()->comEntradasPadrao()->create();
        $mesa = $cash->mesas()->create(['nome' => 'Mesa 1', 'lugares' => 9]);
        foreach ([1, 2] as $lugar) {
            $cash->participantes()->create(['usuario_id' => Usuario::factory()->create()->id, 'jogo_mesa_id' => $mesa->id, 'lugar' => $lugar]);
        }

        $operador = Usuario::factory()->comAcesso()->create();
        $operador->assignRole('Operador');

        try {
            app(IniciarJogo::class)($cash, $operador);
            $this->fail('Operador iniciou');
        } catch (AuthorizationException $erro) {
            $this->assertSame('Só o Admin do site inicia o jogo.', $erro->getMessage());
        }

        app(IniciarJogo::class)($cash, $this->admin);
        $this->assertSame(StatusJogo::EmAndamento, $cash->fresh()->status);
        $this->assertNull($cash->fresh()->fase);
        $this->assertSame(0, $cash->entradas()->count(), 'no cash game cada buy-in é lançado à mão');
    }
}
