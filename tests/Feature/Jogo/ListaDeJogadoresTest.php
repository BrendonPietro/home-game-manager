<?php

namespace Tests\Feature\Jogo;

use App\Actions\Jogo\CadastrarJogadorRapido;
use App\Actions\Jogo\ConfirmarJogador;
use App\Actions\Jogo\DesconfirmarJogador;
use App\Enums\StatusParticipante;
use App\Enums\TipoItem;
use App\Exceptions\RegraDoJogo;
use App\Models\Jogo;
use App\Models\Usuario;
use Database\Seeders\PermissoesSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ListaDeJogadoresTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $operador;

    private Jogo $jogo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissoesSeeder::class);
        $this->operador = Usuario::factory()->comAcesso()->create();
        $this->operador->assignRole('Operador');
        $this->jogo = Jogo::factory()->agendado()->comEntradasPadrao()->create();
    }

    private function confirmar(Usuario $jogador): void
    {
        app(ConfirmarJogador::class)($this->jogo, $jogador, $this->operador);
    }

    public function test_confirmar_poe_na_lista_sem_duplicar(): void
    {
        $joao = Usuario::factory()->create();

        $this->confirmar($joao);
        $this->confirmar($joao);

        $participante = $this->jogo->participantes()->sole();
        $this->assertSame($joao->id, $participante->usuario_id);
        $this->assertSame(StatusParticipante::Confirmado, $participante->fresh()->status);
        $this->assertNull($participante->jogo_mesa_id);
    }

    public function test_quem_nao_tem_permissao_nao_mexe_na_lista(): void
    {
        $semGrupo = Usuario::factory()->comAcesso()->create();

        $this->expectException(AuthorizationException::class);

        app(ConfirmarJogador::class)($this->jogo, Usuario::factory()->create(), $semGrupo);
    }

    public function test_jogo_fora_de_agendado_ou_em_andamento_nao_muda(): void
    {
        $this->jogo->forceFill(['status' => 'finalizado'])->save();

        $this->expectException(RegraDoJogo::class);
        $this->expectExceptionMessage('A lista de jogadores só muda com o jogo agendado ou em andamento.');

        $this->confirmar(Usuario::factory()->create());
    }

    public function test_inativo_nao_entra(): void
    {
        $this->expectException(RegraDoJogo::class);

        $this->confirmar(Usuario::factory()->create(['nome' => 'Fulano', 'apelido' => null, 'ativo' => false]));
    }

    public function test_desconfirmar_tira_da_lista_mas_nao_quem_ja_tem_entrada(): void
    {
        $joao = Usuario::factory()->create(['nome' => 'João', 'apelido' => null]);
        $lucas = Usuario::factory()->create(['nome' => 'Lucas', 'apelido' => null]);
        $this->confirmar($joao);
        $this->confirmar($lucas);

        app(DesconfirmarJogador::class)($this->jogo, $joao, $this->operador);
        $this->assertSame([$lucas->id], $this->jogo->participantes()->pluck('usuario_id')->all());

        // Lucas já tem entrada: o caixa o conhece, não sai.
        $buyin = $this->jogo->itens()->where('tipo', TipoItem::Buyin)->first();
        $this->jogo->entradas()->create([
            'uuid' => (string) Str::uuid(), 'participante_id' => $this->jogo->participantes()->sole()->id,
            'jogo_item_id' => $buyin->id, 'tipo' => TipoItem::Buyin, 'valor' => 100, 'destino' => 'premiacao',
        ]);

        $this->expectException(RegraDoJogo::class);
        $this->expectExceptionMessage('Lucas já tem lançamento nesta noite: não sai da lista.');

        app(DesconfirmarJogador::class)($this->jogo, $lucas, $this->operador);
    }

    public function test_cadastro_rapido_cria_e_confirma(): void
    {
        $participante = app(CadastrarJogadorRapido::class)($this->jogo, '  Zeca   Pagodinho ', '', $this->operador);

        $zeca = $participante->usuario;
        $this->assertSame('Zeca Pagodinho', $zeca->nome);
        $this->assertNull($zeca->apelido);
        $this->assertTrue($zeca->ativo);
        $this->assertFalse($zeca->acessa_site);
        $this->assertSame($this->jogo->id, $participante->jogo_id);
    }

    public function test_cadastro_rapido_nao_duplica_nome_nem_apelido(): void
    {
        Usuario::factory()->create(['nome' => 'Marcos', 'apelido' => 'Marcão']);

        foreach ([['marcos', null], ['Marcão', null], ['Marcos Silva', 'MARCÃO']] as [$nome, $apelido]) {
            try {
                app(CadastrarJogadorRapido::class)($this->jogo, $nome, $apelido, $this->operador);
                $this->fail("Deixou cadastrar {$nome}");
            } catch (RegraDoJogo $erro) {
                $this->assertSame('Já existe Marcão no cadastro: confirme pela lista.', $erro->getMessage());
            }
        }

        $this->assertSame(0, $this->jogo->participantes()->count());
    }

    public function test_cadastro_rapido_pede_permissao(): void
    {
        $semGrupo = Usuario::factory()->comAcesso()->create();

        $this->expectException(AuthorizationException::class);

        app(CadastrarJogadorRapido::class)($this->jogo, 'Zeca', null, $semGrupo);
    }
}
